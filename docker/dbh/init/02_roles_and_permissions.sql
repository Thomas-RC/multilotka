-- Tworzenie ról dla różnych typów dostępu

CREATE ROLE IF NOT EXISTS etl_writer;
CREATE ROLE IF NOT EXISTS analytics_reader;

-- Uprawnienia dla etl_writer (ETL Python worker)
GRANT INSERT, SELECT, ALTER UPDATE ON analytics.draws TO etl_writer;
GRANT INSERT, SELECT ON analytics.draw_combinations TO etl_writer;
GRANT INSERT, SELECT, ALTER UPDATE ON analytics.import_runs TO etl_writer;
GRANT INSERT, SELECT, TRUNCATE ON analytics.combo_aggregates TO etl_writer;

-- Uprawnienia dla analytics_reader (PHP aplikacja - odczyt)
GRANT SELECT ON analytics.* TO analytics_reader;

-- Przypisanie ról do użytkownika multilotka
GRANT etl_writer, analytics_reader TO multilotka;

-- Row policies - filtrowanie danych po import_group_id
CREATE ROW POLICY IF NOT EXISTS analytics_draws_succeeded_only
ON analytics.draws
FOR SELECT
USING import_group_id IN (
    SELECT DISTINCT import_group_id
    FROM analytics.import_runs
    WHERE status = 'succeeded' AND import_group_id > 0
)
TO analytics_reader;

CREATE ROW POLICY IF NOT EXISTS analytics_combinations_succeeded_only
ON analytics.draw_combinations
FOR SELECT
USING import_group_id IN (
    SELECT DISTINCT import_group_id
    FROM analytics.import_runs
    WHERE status = 'succeeded' AND import_group_id > 0
)
TO analytics_reader;

CREATE ROW POLICY IF NOT EXISTS analytics_import_runs_select
ON analytics.import_runs
FOR SELECT
USING 1
TO analytics_reader;
