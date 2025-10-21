<?php

declare(strict_types=1);

namespace Multilotka\Controller;

use Doctrine\DBAL\Connection;
use Multilotka\Core\ClickHouseConnection;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use Multilotka\Stats\ComboStatsRepository;
use Twig\Environment;

final class StatsController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Connection $connection,
        private readonly SessionManager $session,
    ) {
    }

    public function index(): string|RedirectResponse
    {
        if (!$this->session->has('user_id')) {
            return new RedirectResponse('/login');
        }

        $clickhouse = ClickHouseConnection::create();
        $statsRepo = new ComboStatsRepository($clickhouse);

        // Pobierz top 20 kombinacji
        $topCombos = $statsRepo->fetchTopCombinations(20);

        // Pobierz statystyki miesięczne dla top 5 (aby wykres nie był zbyt zagęszczony)
        $top5Combos = array_slice($topCombos, 0, 5);
        $comboStrings = array_map(fn($c) => $c->combo, $top5Combos);

        $monthlyStats = [];
        if (!empty($comboStrings)) {
            $monthlyStats = $statsRepo->fetchMonthlyStats($comboStrings);
        }

        // Przygotuj dane dla wykresu Chart.js
        $chartData = $this->prepareChartData($top5Combos, $monthlyStats);

        return $this->twig->render('stats/index.html.twig', [
            'topCombos' => $topCombos,
            'chartData' => $chartData,
        ]);
    }

    /**
     * Przygotowuje dane dla wykresu Chart.js
     *
     * @param array<\Multilotka\Stats\TopCombo> $combos
     * @param array<string, array<\Multilotka\Stats\MonthlyStats>> $monthlyStats
     * @return array{labels: array<string>, datasets: array<array{label: string, data: array<int>, backgroundColor: string, borderColor: string}>}
     */
    private function prepareChartData(array $combos, array $monthlyStats): array
    {
        if (empty($monthlyStats)) {
            return ['labels' => [], 'datasets' => []];
        }

        // Zbierz wszystkie unikalne miesiące
        $allMonths = [];
        foreach ($monthlyStats as $stats) {
            foreach ($stats as $stat) {
                $monthKey = $stat->month->format('Y-m');
                $allMonths[$monthKey] = $stat->month->format('m/Y');
            }
        }
        ksort($allMonths);
        $labels = array_values($allMonths);

        // Przygotuj kolory dla każdej kombinacji
        $colors = [
            ['rgba(132, 204, 22, 0.8)', 'rgb(132, 204, 22)'],   // lime
            ['rgba(234, 179, 8, 0.8)', 'rgb(234, 179, 8)'],     // yellow
            ['rgba(249, 115, 22, 0.8)', 'rgb(249, 115, 22)'],   // orange
            ['rgba(239, 68, 68, 0.8)', 'rgb(239, 68, 68)'],     // red
            ['rgba(168, 85, 247, 0.8)', 'rgb(168, 85, 247)'],   // purple
        ];

        // Przygotuj datasets dla każdej kombinacji
        $datasets = [];
        $colorIndex = 0;

        foreach ($combos as $combo) {
            $comboKey = $combo->combo;
            if (!isset($monthlyStats[$comboKey])) {
                continue;
            }

            // Utwórz mapę miesiąc => hits
            $monthMap = [];
            foreach ($monthlyStats[$comboKey] as $stat) {
                $monthKey = $stat->month->format('Y-m');
                $monthMap[$monthKey] = $stat->hits;
            }

            // Wypełnij dane dla wszystkich miesięcy (0 jeśli brak)
            $data = [];
            foreach (array_keys($allMonths) as $monthKey) {
                $data[] = $monthMap[$monthKey] ?? 0;
            }

            $colorPair = $colors[$colorIndex % count($colors)];
            $datasets[] = [
                'label' => $comboKey,
                'data' => $data,
                'backgroundColor' => $colorPair[0],
                'borderColor' => $colorPair[1],
                'borderWidth' => 2,
            ];

            $colorIndex++;
        }

        return [
            'labels' => $labels,
            'datasets' => $datasets,
        ];
    }
}
