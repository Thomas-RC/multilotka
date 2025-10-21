<?php

declare(strict_types=1);

namespace Multilotka\Stats;

use ClickHouseDB\Client;
use DateTimeImmutable;

final class ComboStatsRepository
{
    public function __construct(
        private readonly Client $clickhouse,
    ) {
    }

    /**
     * Pobiera top N najczęściej występujących kombinacji
     *
     * @return array<TopCombo>
     */
    public function fetchTopCombinations(int $limit = 20): array
    {
        $result = $this->clickhouse->select(
            <<<SQL
            SELECT
                combo,
                sumMerge(total_hits) as total_hits,
                uniqExactMerge(unique_draws) as unique_draws,
                minMerge(first_draw_date) as first_draw_date,
                maxMerge(last_draw_date) as last_draw_date
            FROM analytics.combo_aggregates
            GROUP BY combo
            ORDER BY total_hits DESC
            LIMIT :limit
            SQL,
            ['limit' => $limit]
        );

        $combos = [];
        foreach ($result->rows() as $row) {
            $combos[] = new TopCombo(
                combo: $row['combo'],
                totalHits: (int) $row['total_hits'],
                uniqueDraws: (int) $row['unique_draws'],
                firstDrawDate: new DateTimeImmutable($row['first_draw_date']),
                lastDrawDate: new DateTimeImmutable($row['last_draw_date']),
            );
        }

        return $combos;
    }

    /**
     * Pobiera występowanie kombinacji w podziale na miesiące
     *
     * @param array<string> $combos Lista kombinacji do analizy
     * @return array<string, array<MonthlyStats>> Tablica [combo => [MonthlyStats, ...]]
     */
    public function fetchMonthlyStats(array $combos): array
    {
        if (empty($combos)) {
            return [];
        }

        $result = $this->clickhouse->select(
            <<<SQL
            SELECT
                combo,
                toStartOfMonth(draw_date) as month,
                sum(count) as hits
            FROM analytics.draw_combinations
            WHERE combo IN (:combos)
            GROUP BY combo, month
            ORDER BY combo, month
            SQL,
            ['combos' => $combos]
        );

        $stats = [];
        foreach ($result->rows() as $row) {
            $combo = $row['combo'];
            if (!isset($stats[$combo])) {
                $stats[$combo] = [];
            }

            $stats[$combo][] = new MonthlyStats(
                month: new DateTimeImmutable($row['month']),
                hits: (int) $row['hits'],
            );
        }

        return $stats;
    }
}
