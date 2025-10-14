1. Tabele z kolumnami, typami danych i ograniczeniami

   **MariaDB (warstwa transakcyjna)**
   - `users`
     - `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
     - `first_name` VARCHAR(80) NOT NULL
     - `last_name` VARCHAR(80) NOT NULL
     - `email` VARCHAR(191) NOT NULL UNIQUE
     - `password_hash` CHAR(60) NOT NULL
     - `confirmation_token` CHAR(64) UNIQUE NULL
     - `status` ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending'
     - `is_admin` TINYINT(1) NOT NULL DEFAULT 0
     - `confirmed_at` DATETIME NULL
     - `last_login_at` DATETIME NULL
     - `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
     - `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
   - `email_verifications`
     - `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
     - `user_id` BIGINT UNSIGNED NOT NULL REFERENCES users(id) ON DELETE CASCADE
     - `token` CHAR(64) NOT NULL UNIQUE
     - `expires_at` DATETIME NOT NULL
     - `confirmed_at` DATETIME NULL
     - `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
   - `password_reset_tokens`
     - `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
     - `user_id` BIGINT UNSIGNED NOT NULL REFERENCES users(id) ON DELETE CASCADE
     - `token` CHAR(64) NOT NULL UNIQUE
     - `expires_at` DATETIME NOT NULL
     - `used_at` DATETIME NULL
     - `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
   - `user_sessions`
     - `id` CHAR(36) PRIMARY KEY
     - `user_id` BIGINT UNSIGNED NOT NULL REFERENCES users(id) ON DELETE CASCADE
     - `ip_address` VARBINARY(16) NULL
     - `user_agent` VARCHAR(255) NULL
     - `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
     - `last_seen_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
     - `is_active` TINYINT(1) NOT NULL DEFAULT 1
   - `uploaded_files`
     - `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
     - `user_id` BIGINT UNSIGNED NULL REFERENCES users(id) ON DELETE SET NULL
     - `original_name` VARCHAR(255) NOT NULL
     - `stored_path` VARCHAR(255) NOT NULL
     - `sha256` CHAR(64) NOT NULL
     - `rows_total` INT UNSIGNED NOT NULL
     - `draw_date_start` DATE NULL
     - `draw_date_end` DATE NULL
     - `status` ENUM('pending','validated','invalid','archived') NOT NULL DEFAULT 'pending'
     - `validation_errors` JSON NULL
     - `uploaded_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
   - `import_jobs`
     - `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
     - `file_id` BIGINT UNSIGNED NOT NULL REFERENCES uploaded_files(id) ON DELETE CASCADE
     - `mode` ENUM('full','incremental') NOT NULL
     - `status` ENUM('queued','running','succeeded','failed','cancelled') NOT NULL DEFAULT 'queued'
     - `progress_percent` TINYINT UNSIGNED NOT NULL DEFAULT 0
     - `current_stage` VARCHAR(50) NULL
     - `draws_processed` INT UNSIGNED NULL
     - `combinations_inserted` BIGINT UNSIGNED NULL
     - `started_at` DATETIME NULL
     - `finished_at` DATETIME NULL
     - `duration_seconds` INT UNSIGNED NULL
     - `etl_request_payload` JSON NULL
     - `etl_response_payload` JSON NULL
     - `error_message` TEXT NULL
     - `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
   - `import_job_events`
     - `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
     - `import_job_id` BIGINT UNSIGNED NOT NULL REFERENCES import_jobs(id) ON DELETE CASCADE
     - `event_type` ENUM('queued','connection_established','batch_inserted','progress_update','completed','failed','cancelled') NOT NULL
     - `payload` JSON NULL
     - `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
   - `tip_batches`
     - `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
     - `import_job_id` BIGINT UNSIGNED NULL REFERENCES import_jobs(id) ON DELETE SET NULL
     - `user_id` BIGINT UNSIGNED NOT NULL REFERENCES users(id) ON DELETE CASCADE
     - `status` ENUM('draft','published','archived') NOT NULL DEFAULT 'draft'
     - `confidence_floor` DECIMAL(5,2) NOT NULL
     - `average_confidence` DECIMAL(5,2) NULL
     - `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
     - `notes` VARCHAR(255) NULL
   - `tip_combinations`
     - `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
     - `tip_batch_id` BIGINT UNSIGNED NOT NULL REFERENCES tip_batches(id) ON DELETE CASCADE
     - `sequence` TINYINT UNSIGNED NOT NULL
     - `combo` CHAR(14) NOT NULL
     - `confidence` DECIMAL(5,2) NOT NULL
     - `due_score` DECIMAL(5,2) NULL
     - `frequency_score` DECIMAL(5,2) NULL
     - `gap_score` DECIMAL(5,2) NULL
     - UNIQUE (`tip_batch_id`,`combo`)
   - `export_files`
     - `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
     - `tip_batch_id` BIGINT UNSIGNED NOT NULL REFERENCES tip_batches(id) ON DELETE CASCADE
     - `file_path` VARCHAR(255) NOT NULL
     - `format` ENUM('csv','json') NOT NULL DEFAULT 'csv'
     - `combination_count` INT UNSIGNED NOT NULL
     - `generated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
     - `generated_by_user_id` BIGINT UNSIGNED NULL REFERENCES users(id) ON DELETE SET NULL
     - `checksum` CHAR(64) NULL
   - `system_events`
     - `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
     - `user_id` BIGINT UNSIGNED NULL REFERENCES users(id) ON DELETE SET NULL
     - `event_type` VARCHAR(50) NOT NULL
     - `context` JSON NULL
     - `ip_address` VARBINARY(16) NULL
     - `occurred_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP

   **ClickHouse (warstwa analityczna)**
   - `analytics.draws`
     - `draw_date` Date NOT NULL
     - `draw_number` UInt32 NOT NULL
     - `numbers` Array(UInt8) NOT NULL
     - `source_file_sha256` FixedString(64) NOT NULL
     - `import_job_id` UInt64 NOT NULL
     - ENGINE = MergeTree()
       - PARTITION BY toYYYYMM(draw_date)
       - ORDER BY (draw_date, draw_number)
       - PRIMARY KEY (draw_date, draw_number)
   - `analytics.draw_combinations`
     - `combo` FixedString(14) NOT NULL
     - `draw_date` Date NOT NULL
     - `draw_number` UInt32 NOT NULL
     - `count` UInt8 NOT NULL DEFAULT 1
     - `import_job_id` UInt64 NOT NULL
     - ENGINE = SummingMergeTree
       - PARTITION BY toYYYYMM(draw_date)
       - ORDER BY (combo, draw_date, draw_number)
       - PRIMARY KEY (combo, draw_date, draw_number)
   - `analytics.combo_aggregates`
     - `combo` FixedString(14) NOT NULL
     - `total_hits` AggregateFunction(sum, UInt64)
     - `unique_draws` AggregateFunction(uniqExact, UInt64)
     - `first_draw_date` AggregateFunction(min, Date)
     - `last_draw_date` AggregateFunction(max, Date)
     - ENGINE = AggregatingMergeTree
       - ORDER BY (combo)
       - PRIMARY KEY (combo)
   - `analytics.combo_aggregates_mv` (MATERIALIZED VIEW)
     - Zasila `analytics.combo_aggregates` stanami agregującymi z `analytics.draw_combinations`
     - Oblicza:
       - `sumState(count)` jako `total_hits`
       - `uniqExactState(toUInt64(toDays(draw_date)) << 32 | draw_number)` jako `unique_draws`
       - `minState(draw_date)` jako `first_draw_date`
       - `maxState(draw_date)` jako `last_draw_date`
   - `analytics.combo_aggregates_view`
     - Widok końcowy prezentujący dane z `analytics.combo_aggregates` po `*Merge`
     - Kolumny: `combo`, `total_hits`, `unique_draws`, `first_draw_date`, `last_draw_date`, `current_gap_days`
     - `current_gap_days` wyliczane w locie: `dateDiff('day', maxMerge(last_draw_date), today())`
   - `analytics.import_runs`
     - `import_job_id` UInt64 NOT NULL
     - `mode` Enum8('full'=1,'incremental'=2) NOT NULL
     - `source_file_sha256` FixedString(64) NOT NULL
     - `draws_loaded` UInt32 NOT NULL
     - `combinations_loaded` UInt64 NOT NULL
     - `started_at` DateTime NOT NULL
     - `finished_at` DateTime NOT NULL
     - `status` Enum8('running'=1,'succeeded'=2,'failed'=3) NOT NULL
     - `error_message` String
     - ENGINE = MergeTree()
       - ORDER BY (started_at, import_job_id)

2. Relacje między tabelami

   **MariaDB**
   - `email_verifications`, `password_reset_tokens`, `user_sessions`, `tip_batches`, `system_events` oraz `export_files` wskazują na `users.id`.
   - `uploaded_files` łączy się z `users.id` (autor uploadu).
   - `import_jobs` oraz `import_job_events` łączą się z `uploaded_files.id` i pośrednio z `users`.
   - `tip_batches` odnosi się do `import_jobs.id`, dzięki czemu rekomendacje są powiązane z konkretnym importem.
   - `tip_combinations` wiąże się z `tip_batches.id`, wymuszając spójność zestawu rekomendacji.
   - `export_files` wskazuje na `tip_batches.id`, zapewniając śledzenie źródła eksportu.
   - `system_events` opcjonalnie referuje `users.id` oraz przechowuje kontekst zdarzeń FR-15.

   **Powiązania MariaDB ↔ ClickHouse**
   - `analytics.draws.import_job_id` i `analytics.draw_combinations.import_job_id` odpowiadają `import_jobs.id` (przekazywane jako UInt64), co umożliwia mapowanie metadanych z warstwy transakcyjnej.
   - `analytics.import_runs.import_job_id` synchronizuje się z `import_jobs.id`, pozwalając zderzyć statystyki ETL z danymi panelu.

3. Indeksy

   **MariaDB**
   - `users(email)` UNIQUE, `users(status)`
   - `email_verifications(user_id, token)`, `password_reset_tokens(user_id, token)`
   - `user_sessions(user_id, is_active)`
   - `uploaded_files(user_id, status, uploaded_at)`
   - `import_jobs(file_id, status, created_at)`, `import_jobs(mode, status)`
   - `import_job_events(import_job_id, created_at)`
   - `tip_batches(user_id, generated_at)`
   - `tip_combinations(tip_batch_id, confidence DESC)`
   - `export_files(tip_batch_id, generated_at)`
   - `system_events(event_type, occurred_at)`

   **ClickHouse**
   - Klucze porządkujące i partycjonowanie zdefiniowane w silnikach MergeTree/SummingMergeTree pełnią rolę indeksów podstawowych.
   - Materializowane agregaty oraz widok `analytics.combo_aggregates_view` zapewniają preindeksowanie wyników zapytań top-N; filtrowanie po luce można wykonywać na widoku (kolumna obliczana).

4. Zasady ClickHouse

   - Utworzyć role:
     - `etl_writer` z uprawnieniami INSERT do `analytics.draws`, `analytics.draw_combinations` oraz `analytics.import_runs`.
     - `analytics_reader` z uprawnieniami SELECT do wszystkich tabel w schemacie `analytics`.
   - Zdefiniować polityki wierszy:
     - `CREATE ROW POLICY analytics_reader_policy ON analytics.draws FOR SELECT USING import_job_id IN (SELECT id FROM maria.import_jobs WHERE status='succeeded');`
     - Analogiczna polityka na `analytics.draw_combinations` i `analytics.import_runs`, ograniczająca widoczność do zakończonych importów.
   - Wymusić TLS dla połączeń z ClickHouse oraz przechowywać poświadczenia w zmiennych środowiskowych Dockera (`CLICKHOUSE_USER`, `CLICKHOUSE_PASSWORD`).
   - Włączyć `readonly=1` dla konta analitycznego w konfiguracji ClickHouse (`users.d/default/readonly.xml`), aby dashboard nie mógł mutować danych.

5. Dodatkowe uwagi i wyjaśnienia

   - Rozważyć tabelę archiwalną lub politykę TTL w `analytics.draw_combinations` (np. `TTL draw_date + INTERVAL 5 YEAR`) po potwierdzeniu wymagań retencji.
   - `validation_errors` w `uploaded_files` przechowuje listę problemów walidacyjnych dla raportowania FR-12/13; dane można serializować do JSON.
   - `system_events` zapewnia pełne pokrycie FR-15 i metryk MT-01…MT-04; indeksowanie po `event_type` umożliwi łatwe raportowanie.
   - `tip_combinations.combo` wykorzystuje format `NN-NN-NN-NN-NN` spójny z ClickHouse (`FixedString(14)`), co pozwala na łatwe porównania między bazami.
   - `analytics.combo_aggregates_mv` może zostać rozszerzony o dodatkowe kolumny (np. rolling window) w kolejnych iteracjach; aktualna definicja pokrywa wymagania FR-16.
   - W kwestiach nierozstrzygniętych (staging, dodatkowe metadane ETL, rozbudowane role) należy przygotować kolejny warsztat architektoniczny przed skalowaniem rozwiązania.
