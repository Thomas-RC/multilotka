<?php

declare(strict_types=1);

namespace Multilotka\Tip;

use ClickHouseDB\Client as ClickHouseClient;
use DateTimeImmutable;

final class ComboStatisticsRepository implements ComboStatisticsRepositoryInterface
{
    public function __construct(
        private readonly ClickHouseClient $clickhouse,
    ) {
    }

    /**
     * Pobiera statystyki kombinacji z ClickHouse
     *
     * @param int $limit Maksymalna liczba kombinacji do pobrania
     * @return array<ComboStatistics>
     */
    public function fetchTopCombinations(int $limit = 1000): array
    {
        // CACHE: Czytamy z combo_aggregates (pre-computed cache)
        // Cache jest odświeżany po każdym imporcie przez ETL worker
        // Zapytanie wykonuje się natychmiastowo (~1-2s) zamiast 20-60s
        $query = <<<SQL
            SELECT
                combo,
                total_hits,
                unique_draws,
                first_draw_date,
                last_draw_date,
                dateDiff('day', last_draw_date, today()) AS current_gap_days
            FROM (
                SELECT
                    combo,
                    sumMerge(total_hits) AS total_hits,
                    uniqMerge(unique_draws) AS unique_draws,
                    minMerge(first_draw_date) AS first_draw_date,
                    maxMerge(last_draw_date) AS last_draw_date
                FROM analytics.combo_aggregates
                GROUP BY combo
            )
            ORDER BY total_hits DESC, current_gap_days DESC
            LIMIT :limit
        SQL;

        $result = $this->clickhouse->select($query, ['limit' => $limit]);

        $statistics = [];
        foreach ($result->rows() as $row) {
            $statistics[] = new ComboStatistics(
                combo: $row['combo'],
                totalHits: (int) $row['total_hits'],
                uniqueDraws: (int) $row['unique_draws'],
                firstDrawDate: new DateTimeImmutable($row['first_draw_date']),
                lastDrawDate: new DateTimeImmutable($row['last_draw_date']),
                currentGapDays: (int) $row['current_gap_days'],
            );
        }

        return $statistics;
    }

    /**
     * Pobiera całkowitą liczbę losowań w bazie
     */
    public function getTotalDrawsCount(): int
    {
        $query = 'SELECT count(DISTINCT draw_number) as total FROM analytics.draws';
        $result = $this->clickhouse->select($query);
        $rows = $result->rows();

        return isset($rows[0]['total']) ? (int) $rows[0]['total'] : 0;
    }

    /**
     * Pobiera najnowszą datę losowania
     */
    public function getLatestDrawDate(): ?DateTimeImmutable
    {
        $query = 'SELECT max(draw_date) as latest FROM analytics.draws';
        $result = $this->clickhouse->select($query);
        $rows = $result->rows();

        if (isset($rows[0]['latest']) && !empty($rows[0]['latest'])) {
            return new DateTimeImmutable($rows[0]['latest']);
        }

        return null;
    }
}
