CREATE DATABASE IF NOT EXISTS analytics;

CREATE TABLE IF NOT EXISTS analytics.draws
(
    draw_date Date NOT NULL,
    draw_number UInt32 NOT NULL,
    numbers Array(UInt8) NOT NULL,
    source_file_sha256 FixedString(64) NOT NULL,
    import_job_id UInt64 NOT NULL,
    import_group_id UInt64 NOT NULL DEFAULT 0
)
ENGINE = MergeTree
PARTITION BY toYYYYMM(draw_date)
ORDER BY (draw_date, draw_number);

CREATE TABLE IF NOT EXISTS analytics.draw_combinations
(
    combo FixedString(14) NOT NULL,
    draw_date Date NOT NULL,
    draw_number UInt32 NOT NULL,
    count UInt8 NOT NULL DEFAULT 1,
    import_job_id UInt64 NOT NULL,
    import_group_id UInt64 NOT NULL DEFAULT 0
)
ENGINE = SummingMergeTree
PARTITION BY toYYYYMM(draw_date)
ORDER BY (combo, draw_date, draw_number);

CREATE TABLE IF NOT EXISTS analytics.combo_aggregates
(
    combo FixedString(14) NOT NULL,
    total_hits AggregateFunction(sum, UInt64),
    unique_draws AggregateFunction(uniqExact, UInt64),
    first_draw_date AggregateFunction(min, Date),
    last_draw_date AggregateFunction(max, Date)
)
ENGINE = AggregatingMergeTree
ORDER BY combo;

CREATE TABLE IF NOT EXISTS analytics.import_runs
(
    import_job_id UInt64 NOT NULL,
    import_group_id UInt64 NOT NULL DEFAULT 0,
    mode Enum8('full' = 1, 'incremental' = 2) NOT NULL,
    source_file_sha256 FixedString(64) NOT NULL,
    draws_loaded UInt32 NOT NULL,
    combinations_loaded UInt64 NOT NULL,
    started_at DateTime NOT NULL,
    finished_at DateTime NOT NULL,
    status Enum8('running' = 1, 'succeeded' = 2, 'failed' = 3) NOT NULL,
    error_message String DEFAULT ''
)
ENGINE = MergeTree
ORDER BY (started_at, import_job_id);

CREATE MATERIALIZED VIEW IF NOT EXISTS analytics.combo_aggregates_mv
TO analytics.combo_aggregates
AS
SELECT
    combo,
    sumState(toUInt64(count)) AS total_hits,
    uniqExactState(
        bitShiftLeft(toUInt64(toRelativeDayNum(draw_date)), 32) + toUInt64(draw_number)
    ) AS unique_draws,
    minState(draw_date) AS first_draw_date,
    maxState(draw_date) AS last_draw_date
FROM analytics.draw_combinations
GROUP BY combo;

CREATE VIEW IF NOT EXISTS analytics.combo_aggregates_view
AS
SELECT
    combo,
    total_hits,
    unique_draws,
    first_draw_date,
    last_draw_date,
    toUInt16(greatest(0, dateDiff('day', last_draw_date, today()))) AS current_gap_days
FROM
(
    SELECT
        combo,
        sumMerge(total_hits) AS total_hits,
        uniqExactMerge(unique_draws) AS unique_draws,
        minMerge(first_draw_date) AS first_draw_date,
        maxMerge(last_draw_date) AS last_draw_date
    FROM analytics.combo_aggregates
    GROUP BY combo
);

CREATE ROLE IF NOT EXISTS etl_writer;
CREATE ROLE IF NOT EXISTS analytics_reader;

GRANT INSERT ON analytics.draws TO etl_writer;
GRANT SELECT ON analytics.draws TO etl_writer;
GRANT INSERT ON analytics.draw_combinations TO etl_writer;
GRANT INSERT ON analytics.combo_aggregates TO etl_writer;
GRANT INSERT ON analytics.import_runs TO etl_writer;
GRANT SELECT ON analytics.import_runs TO etl_writer;
GRANT ALTER UPDATE ON analytics.import_runs TO etl_writer;
GRANT SELECT ON analytics.* TO analytics_reader;

GRANT etl_writer TO multilotka;
GRANT analytics_reader TO multilotka;

CREATE ROW POLICY IF NOT EXISTS analytics_draws_succeeded_only
ON analytics.draws
FOR SELECT
USING import_job_id IN (
    SELECT import_job_id FROM analytics.import_runs WHERE status = 'succeeded'
)
TO analytics_reader;

CREATE ROW POLICY IF NOT EXISTS analytics_combinations_succeeded_only
ON analytics.draw_combinations
FOR SELECT
USING import_job_id IN (
    SELECT import_job_id FROM analytics.import_runs WHERE status = 'succeeded'
)
TO analytics_reader;

CREATE ROW POLICY IF NOT EXISTS analytics_import_runs_select
ON analytics.import_runs
FOR SELECT
USING status = 'succeeded'
TO analytics_reader;
