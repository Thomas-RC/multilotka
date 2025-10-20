import itertools
import json
import os
import re
import time
from datetime import date, datetime
from typing import Any, Dict, Iterable, List, Optional, Sequence, Tuple

import pymysql
from clickhouse_driver import Client

COMBINATION_SIZE = 5
EXPECTED_NUMBERS = 20
NUMBER_MIN = 1
NUMBER_MAX = 80
DRAW_BATCH_SIZE = 64
COMBO_BATCH_SIZE = 5_000
LINE_PATTERN = re.compile(
    r"^\s*(?P<number>\d+)\.\s+(?P<date>\d{2}\.\d{2}\.\d{4})\s+(?P<numbers>\d+(?:\s*,\s*\d+)*)\s*$"
)


def get_db_connection() -> pymysql.connections.Connection:
    return pymysql.connect(
        host=os.getenv("MARIADB_HOST", "db"),
        port=int(os.getenv("MARIADB_PORT", "3306")),
        user=os.getenv("MARIADB_USER", "root"),
        password=os.getenv("MARIADB_PASSWORD", ""),
        database=os.getenv("MARIADB_DATABASE", "multilotka"),
        charset="utf8mb4",
        autocommit=True,
        cursorclass=pymysql.cursors.DictCursor,
    )


def get_clickhouse_client() -> Client:
    return Client(
        host=os.getenv("CLICKHOUSE_HOST", "dbh"),
        port=int(os.getenv("CLICKHOUSE_PORT", "9000")),
        user=os.getenv("CLICKHOUSE_USER", "default"),
        password=os.getenv("CLICKHOUSE_PASSWORD", ""),
        database=os.getenv("CLICKHOUSE_DATABASE", "analytics"),
        secure=os.getenv("CLICKHOUSE_SECURE", "0") in {"1", "true", "TRUE"},
        send_receive_timeout=int(os.getenv("CLICKHOUSE_TIMEOUT", "30")),
        connect_timeout=int(os.getenv("CLICKHOUSE_CONNECT_TIMEOUT", "10")),
        settings={"use_numpy": False},
    )


def log(message: str) -> None:
    timestamp = datetime.utcnow().isoformat()
    print(f"[{timestamp}] {message}", flush=True)


def insert_event(
    cur: pymysql.cursors.Cursor,
    job_id: int,
    event_type: str,
    payload: Optional[Dict[str, Any]] = None,
) -> None:
    cur.execute(
        """
        INSERT INTO import_job_events (import_job_id, event_type, payload, created_at)
        VALUES (%s, %s, %s, NOW())
        """,
        (job_id, event_type, json.dumps(payload) if payload is not None else None),
    )


def update_job(
    cur: pymysql.cursors.Cursor,
    job_id: int,
    *,
    status: Optional[str] = None,
    stage: Optional[str] = None,
    progress: Optional[int] = None,
    draws_processed: Optional[int] = None,
    combinations_inserted: Optional[int] = None,
    error_message: Optional[str] = None,
    mark_started: bool = False,
    mark_finished: bool = False,
) -> None:
    fields: List[str] = []
    params: List[Any] = []

    if status is not None:
        fields.append("status = %s")
        params.append(status)
    if stage is not None:
        fields.append("current_stage = %s")
        params.append(stage)
    if progress is not None:
        fields.append("progress_percent = %s")
        params.append(progress)
    if draws_processed is not None:
        fields.append("draws_processed = %s")
        params.append(draws_processed)
    if combinations_inserted is not None:
        fields.append("combinations_inserted = %s")
        params.append(combinations_inserted)
    if error_message is not None:
        fields.append("error_message = %s")
        params.append(error_message)
    if mark_started:
        fields.append("started_at = NOW()")
    if mark_finished:
        fields.append("finished_at = NOW()")
        fields.append("duration_seconds = TIMESTAMPDIFF(SECOND, started_at, NOW())")

    if not fields:
        return

    params.append(job_id)
    cur.execute(
        f"UPDATE import_jobs SET {', '.join(fields)} WHERE id = %s",
        tuple(params),
    )


def resolve_file_path(payload: Dict[str, Any]) -> Optional[str]:
    candidates: List[str] = []

    absolute_path = payload.get("absolute_path")
    if isinstance(absolute_path, str):
        candidates.append(absolute_path)

    relative_path = payload.get("relative_path")
    if isinstance(relative_path, str):
        workspace = os.getenv("WORKSPACE_PATH", "/opt/multilotka/app")
        candidates.append(os.path.join(workspace, relative_path))
        repo_root = os.getenv("PROJECT_ROOT", "/opt/multilotka")
        candidates.append(os.path.join(repo_root, relative_path))

    for candidate in candidates:
        if candidate and os.path.isfile(candidate):
            return candidate

    return None


def count_draw_lines(path: str) -> int:
    with open(path, "r", encoding="utf-8") as handle:
        return sum(1 for line in handle if line.strip())


def parse_draw_line(raw_line: str, line_number: int) -> Tuple[int, date, List[int]]:
    match = LINE_PATTERN.match(raw_line)
    if not match:
        raise ValueError(f"Linia {line_number}: nie rozpoznano formatu wiersza.")

    draw_number = int(match.group("number"))
    draw_date = datetime.strptime(match.group("date"), "%d.%m.%Y").date()
    numbers_raw = [part.strip() for part in match.group("numbers").split(",")]

    if len(numbers_raw) != EXPECTED_NUMBERS:
        raise ValueError(
            f"Linia {line_number}: oczekiwano {EXPECTED_NUMBERS} liczb w losowaniu."
        )

    numbers: List[int] = []
    seen = set()
    for token in numbers_raw:
        if not token.isdigit():
            raise ValueError(
                f"Linia {line_number}: wszystkie wartości muszą być liczbami całkowitymi."
            )
        value = int(token)
        if value < NUMBER_MIN or value > NUMBER_MAX:
            raise ValueError(
                f"Linia {line_number}: liczby muszą zawierać się w zakresie {NUMBER_MIN}-{NUMBER_MAX}."
            )
        if value in seen:
            raise ValueError(
                f"Linia {line_number}: liczby w losowaniu muszą być unikalne."
            )
        seen.add(value)
        numbers.append(value)

    numbers.sort()
    return draw_number, draw_date, numbers


def format_combo(combo: Sequence[int]) -> str:
    return "-".join(f"{value:02d}" for value in combo)


def chunked(iterable: Iterable[Any], size: int) -> Iterable[List[Any]]:
    buffer: List[Any] = []
    for item in iterable:
        buffer.append(item)
        if len(buffer) >= size:
            yield buffer
            buffer = []
    if buffer:
        yield buffer


def insert_draws(client: Client, rows: List[Tuple[date, int, List[int], str, int]]) -> None:
    if not rows:
        return
    client.execute(
        """
        INSERT INTO analytics.draws (draw_date, draw_number, numbers, source_file_sha256, import_job_id)
        VALUES
        """,
        rows,
    )


def insert_combinations(
    client: Client,
    rows: List[Tuple[str, date, int, int, int]],
) -> None:
    if not rows:
        return
    client.execute(
        """
        INSERT INTO analytics.draw_combinations (combo, draw_date, draw_number, count, import_job_id)
        VALUES
        """,
        rows,
    )


def register_import_run(
    client: Client,
    job_id: int,
    mode: str,
    file_sha256: str,
    started_at: datetime,
) -> None:
    client.execute(
        """
        INSERT INTO analytics.import_runs (
            import_job_id,
            mode,
            source_file_sha256,
            draws_loaded,
            combinations_loaded,
            started_at,
            finished_at,
            status,
            error_message
        )
        VALUES
        """,
        [
            (
                job_id,
                mode,
                file_sha256,
                0,
                0,
                started_at,
                started_at,
                "running",
                "",
            )
        ],
    )


def update_import_run_state(
    client: Client,
    job_id: int,
    *,
    status: str,
    draws_loaded: int,
    combos_loaded: int,
    finished_at: Optional[datetime] = None,
    error_message: str = "",
) -> None:
    client.execute(
        """
        ALTER TABLE analytics.import_runs
        UPDATE
            status = %(status)s,
            draws_loaded = %(draws)s,
            combinations_loaded = %(combos)s,
            finished_at = %(finished)s,
            error_message = %(error)s
        WHERE import_job_id = %(job_id)s
        """,
        {
            "status": status,
            "draws": draws_loaded,
            "combos": combos_loaded,
            "finished": finished_at or datetime.utcnow(),
            "error": error_message,
            "job_id": job_id,
        },
    )


def fetch_max_draw_number(client: Client) -> int:
    result = client.execute(
        """
        SELECT max(draw_number)
        FROM analytics.draws
        WHERE import_job_id IN (
            SELECT import_job_id
            FROM analytics.import_runs
            WHERE status = 'succeeded'
        )
        """
    )
    if not result:
        return 0
    max_value = result[0][0]
    return int(max_value) if max_value is not None else 0


def mark_previous_runs_failed(client: Client, job_id: int) -> None:
    client.execute(
        """
        ALTER TABLE analytics.import_runs
        UPDATE status = 'failed'
        WHERE import_job_id != %(job_id)s AND status = 'succeeded'
        """,
        {"job_id": job_id},
    )


def process_job(conn: pymysql.connections.Connection, job: Dict[str, Any]) -> None:
    job_id = int(job["id"])
    request_payload = json.loads(job.get("etl_request_payload") or "{}")
    absolute_path = resolve_file_path(request_payload)

    if not absolute_path:
        log(f"Job {job_id}: file not found for payload {request_payload}")
        with conn.cursor() as cur:
            update_job(
                cur,
                job_id,
                status="failed",
                error_message="Plik importu nie istnieje.",
                progress=0,
                stage="error",
                mark_started=True,
                mark_finished=True,
            )
            insert_event(
                cur,
                job_id,
                "failed",
                {"message": "Nie udało się odszukać pliku na dysku."},
            )
        return

    file_sha256 = request_payload.get("file_sha256")
    if not isinstance(file_sha256, str) or not file_sha256:
        log(f"Job {job_id}: missing file SHA-256 in payload {request_payload}")
        with conn.cursor() as cur:
            update_job(
                cur,
                job_id,
                status="failed",
                error_message="Brak sumy kontrolnej pliku w żądaniu ETL.",
                progress=0,
                stage="error",
                mark_started=True,
                mark_finished=True,
            )
            insert_event(
                cur,
                job_id,
                "failed",
                {"message": "Brak sumy kontrolnej pliku w żądaniu ETL."},
            )
        return

    mode = request_payload.get("mode", "full")
    if mode not in {"full", "incremental"}:
        log(f"Job {job_id}: unsupported import mode '{mode}'")
        with conn.cursor() as cur:
            update_job(
                cur,
                job_id,
                status="failed",
                error_message=f"Nieobsługiwany tryb importu: {mode}",
                progress=0,
                stage="error",
                mark_started=True,
                mark_finished=True,
            )
            insert_event(
                cur,
                job_id,
                "failed",
                {"message": f"Nieobsługiwany tryb importu: {mode}."},
            )
        return

    log(f"Job {job_id}: starting import for {absolute_path} ({mode})")
    started_at = datetime.utcnow()

    clickhouse_client: Optional[Client] = None
    draws_processed = 0
    combos_inserted = 0
    skipped_draws = 0
    line_total = 0
    lines_processed = 0

    try:
        clickhouse_client = get_clickhouse_client()
        existing_max_draw_number = (
            fetch_max_draw_number(clickhouse_client) if mode == "incremental" else 0
        )

        register_import_run(clickhouse_client, job_id, mode, file_sha256, started_at)

        with conn.cursor() as cur:
            update_job(
                cur,
                job_id,
                status="running",
                stage="preparing",
                progress=5,
                error_message=None,
                mark_started=True,
            )
            insert_event(
                cur,
                job_id,
                "connection_established",
                {
                    "message": "ETL nawiązał połączenia i rozpoczyna analizę pliku.",
                    "mode": mode,
                    "existing_max_draw_number": existing_max_draw_number,
                },
            )

        line_total = count_draw_lines(absolute_path)
        if line_total == 0:
            raise RuntimeError("Plik nie zawiera losowań.")

        with conn.cursor() as cur:
            update_job(cur, job_id, stage="parsing", progress=10)

        draw_batch: List[Tuple[date, int, List[int], str, int]] = []
        combo_batch: List[Tuple[str, date, int, int, int]] = []

        def flush_draw_batch() -> None:
            nonlocal draw_batch
            if draw_batch:
                insert_draws(clickhouse_client, draw_batch)
                draw_batch = []

        def flush_combo_batch() -> None:
            nonlocal combo_batch, combos_inserted
            if combo_batch:
                batch = combo_batch
                combo_batch = []
                insert_combinations(clickhouse_client, batch)
                combos_inserted += len(batch)

        with open(absolute_path, "r", encoding="utf-8") as handle:
            for line_number, raw_line in enumerate(handle, start=1):
                stripped = raw_line.strip()
                if not stripped:
                    continue

                lines_processed += 1
                draw_number, draw_date, numbers = parse_draw_line(stripped, line_number)

                if mode == "incremental" and draw_number <= existing_max_draw_number:
                    skipped_draws += 1
                    continue

                draw_batch.append((draw_date, draw_number, numbers, file_sha256, job_id))
                draws_processed += 1

                for combo in itertools.combinations(numbers, COMBINATION_SIZE):
                    combo_batch.append((format_combo(combo), draw_date, draw_number, 1, job_id))
                    if len(combo_batch) >= COMBO_BATCH_SIZE:
                        flush_combo_batch()

                if len(draw_batch) >= DRAW_BATCH_SIZE:
                    flush_draw_batch()

                if (
                    draws_processed % 10 == 0
                    or lines_processed == line_total
                    or draws_processed == 1
                ):
                    percent = min(
                        80,
                        10 + int(lines_processed / line_total * 60),
                    )
                    with conn.cursor() as cur:
                        update_job(
                            cur,
                            job_id,
                            progress=percent,
                            stage="processing",
                            draws_processed=draws_processed,
                            combinations_inserted=combos_inserted,
                        )
                        insert_event(
                            cur,
                            job_id,
                            "progress_update",
                            {
                                "percent": percent,
                                "processed_draws": draws_processed,
                                "skipped_draws": skipped_draws,
                            },
                        )

        flush_draw_batch()
        flush_combo_batch()

        with conn.cursor() as cur:
            update_job(
                cur,
                job_id,
                stage="finalizing",
                progress=95,
                draws_processed=draws_processed,
                combinations_inserted=combos_inserted,
            )

        update_import_run_state(
            clickhouse_client,
            job_id,
            status="succeeded",
            draws_loaded=draws_processed,
            combos_loaded=combos_inserted,
            finished_at=datetime.utcnow(),
            error_message="",
        )

        if mode == "full":
            mark_previous_runs_failed(clickhouse_client, job_id)

        message_parts = [
            f"Import zakończony. Przetworzono {draws_processed} losowań.",
            f"Wygenerowano {combos_inserted} kombinacji pięcioelementowych.",
        ]
        if skipped_draws > 0:
            message_parts.append(f"Pominięto {skipped_draws} losowań (już istnieją).")

        with conn.cursor() as cur:
            update_job(
                cur,
                job_id,
                status="succeeded",
                stage="completed",
                progress=100,
                draws_processed=draws_processed,
                combinations_inserted=combos_inserted,
                error_message=None,
                mark_finished=True,
            )
            insert_event(
                cur,
                job_id,
                "completed",
                {"message": " ".join(message_parts)},
            )

        log(
            f"Job {job_id}: completed successfully "
            f"(draws={draws_processed}, combos={combos_inserted}, skipped={skipped_draws})"
        )

    except Exception as exc:  # noqa: BLE001
        log(f"Job {job_id}: failed with error: {exc}")
        error_text = str(exc)
        if clickhouse_client is not None:
            try:
                update_import_run_state(
                    clickhouse_client,
                    job_id,
                    status="failed",
                    draws_loaded=draws_processed,
                    combos_loaded=combos_inserted,
                    finished_at=datetime.utcnow(),
                    error_message=error_text,
                )
            except Exception as nested_exc:  # noqa: BLE001
                log(f"Job {job_id}: unable to update import_runs status ({nested_exc})")

        with conn.cursor() as cur:
            update_job(
                cur,
                job_id,
                status="failed",
                stage="error",
                error_message=error_text,
                progress=0,
                draws_processed=draws_processed or None,
                combinations_inserted=combos_inserted or None,
                mark_finished=True,
            )
            insert_event(cur, job_id, "failed", {"message": error_text})

    finally:
        if clickhouse_client is not None:
            clickhouse_client.disconnect()


def fetch_next_job(conn: pymysql.connections.Connection) -> Optional[Dict[str, Any]]:
    with conn.cursor() as cur:
        cur.execute(
            """
            SELECT *
            FROM import_jobs
            WHERE status = 'queued'
            ORDER BY created_at ASC, id ASC
            LIMIT 1
            """,
        )
        row = cur.fetchone()

    return row


def main() -> None:
    log("ETL worker started")
    conn = None

    while True:
        try:
            if conn is None or not conn.open:
                conn = get_db_connection()

            job = fetch_next_job(conn)
            if job is None:
                time.sleep(2)
                continue

            process_job(conn, job)

        except pymysql.MySQLError as db_error:
            log(f"Database error: {db_error}")
            time.sleep(5)
            conn = None
        except Exception as exc:  # noqa: BLE001
            log(f"Worker error: {exc}")
            time.sleep(5)


if __name__ == "__main__":
    main()
