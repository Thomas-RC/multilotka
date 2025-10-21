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
        // CACHE: Czytamy z combo_aggregates (pre-computed cache)
        // Cache jest odświeżany po każdym imporcie przez ETL worker
        // Zapytanie wykonuje się natychmiastowo (~0.1s) zamiast 20-60s
        $result = $this->clickhouse->select(
            <<<SQL
            SELECT
                combo,
                sumMerge(total_hits) AS total_hits,
                uniqMerge(unique_draws) AS unique_draws,
                minMerge(first_draw_date) AS first_draw_date,
                maxMerge(last_draw_date) AS last_draw_date
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
     * Pobiera występowanie kombinacji w podziale na miesiące (agregacja wszystkich lat)
     * Np. wszystkie październiki razem, wszystkie listopady razem, itd.
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
                toMonth(draw_date) as month_num,
                sum(count) as hits
            FROM analytics.draw_combinations
            WHERE combo IN (:combos)
            GROUP BY combo, month_num
            ORDER BY combo, month_num
            SQL,
            ['combos' => $combos]
        );

        $stats = [];
        foreach ($result->rows() as $row) {
            $combo = $row['combo'];
            if (!isset($stats[$combo])) {
                $stats[$combo] = [];
            }

            // Tworzymy DateTimeImmutable z numerem miesiąca (1-12)
            // Używamy roku 2000 jako placeholder
            $monthNum = (int) $row['month_num'];
            $stats[$combo][] = new MonthlyStats(
                month: new DateTimeImmutable(sprintf('2000-%02d-01', $monthNum)),
                hits: (int) $row['hits'],
            );
        }

        return $stats;
    }
}
