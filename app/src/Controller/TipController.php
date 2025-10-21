<?php

declare(strict_types=1);

namespace Multilotka\Controller;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Multilotka\Core\ClickHouseConnection;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use Multilotka\Tip\ComboStatisticsRepository;
use Multilotka\Tip\TipBatch;
use Multilotka\Tip\TipBatchRepository;
use Multilotka\Tip\TipCombination;
use Multilotka\Tip\TipCombinationRepository;
use Multilotka\Tip\TipGenerator;
use Twig\Environment;

final class TipController
{
    private readonly TipGenerator $generator;
    private readonly TipBatchRepository $batchRepository;
    private readonly TipCombinationRepository $combinationRepository;

    public function __construct(
        private readonly Environment $twig,
        private readonly Connection $connection,
        private readonly SessionManager $session,
    ) {
        // Inicjalizacja klienta ClickHouse z .env
        $clickhouse = ClickHouseConnection::create();

        $statsRepository = new ComboStatisticsRepository($clickhouse);
        $this->generator = new TipGenerator($statsRepository);
        $this->batchRepository = new TipBatchRepository($connection);
        $this->combinationRepository = new TipCombinationRepository($connection);
    }

    /**
     * POST /dashboard/tips/generate
     * Generuje nowy zestaw tipów
     */
    public function generate(): string|RedirectResponse
    {
        $userId = $this->session->get('user_id');
        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        try {
            // Generuj tipy
            $scoredCombinations = $this->generator->generateTips();

            if (empty($scoredCombinations)) {
                $this->session->flash('error', 'Nie udało się wygenerować tipów. Spróbuj ponownie.');

                return new RedirectResponse('/dashboard/tips');
            }

            // Oblicz średnią confidence
            $totalConfidence = array_reduce(
                $scoredCombinations,
                fn($sum, $combo) => $sum + $combo->confidence(),
                0.0
            );
            $averageConfidence = round($totalConfidence / count($scoredCombinations), 2);

            // Pobierz ostatni import_job_id
            $lastImportJobId = $this->getLastSuccessfulImportJobId();

            // Utwórz batch
            $batch = TipBatch::create(
                userId: (int) $userId,
                importJobId: $lastImportJobId,
                confidenceFloor: 50.0,
                generatedAt: new DateTimeImmutable('now'),
                notes: 'Automatycznie wygenerowany zestaw tipów',
            );

            $batch = $batch->withAverageConfidence($averageConfidence);
            $batch = $this->batchRepository->save($batch);

            // Zapisz kombinacje
            $combinations = [];
            foreach ($scoredCombinations as $index => $scored) {
                $combinations[] = TipCombination::create(
                    tipBatchId: $batch->id(),
                    sequence: $index + 1,
                    combo: $scored->combo(),
                    confidence: $scored->confidence(),
                    dueScore: $scored->dueScore(),
                    frequencyScore: $scored->frequencyScore(),
                    gapScore: $scored->gapScore(),
                );
            }

            $this->combinationRepository->saveMany($combinations);

            // Opublikuj batch
            $batch = $batch->publish();
            $this->batchRepository->save($batch);

            $this->session->flash('success', sprintf(
                'Wygenerowano %d tipów ze średnią pewnością %.2f%%',
                count($scoredCombinations),
                $averageConfidence
            ));

            return new RedirectResponse('/dashboard/tips');
        } catch (\RuntimeException $e) {
            $this->session->flash('error', 'Błąd generowania tipów: ' . $e->getMessage());

            return new RedirectResponse('/dashboard/tips');
        }
    }

    /**
     * GET /dashboard/tips
     * Wyświetla ostatni zestaw tipów
     */
    public function index(): string|RedirectResponse
    {
        $userId = $this->session->get('user_id');
        if ($userId === null) {
            return new RedirectResponse('/login');
        }

        $flashes = $this->session->allFlashes();
        $latestBatch = $this->batchRepository->findLatestByUser((int) $userId);

        if ($latestBatch === null) {
            return $this->twig->render('tips/index.html.twig', [
                'batch' => null,
                'combinations' => [],
                'user_email' => $this->session->get('user_email'),
                'flashes' => $flashes,
            ]);
        }

        $combinations = $this->combinationRepository->findByBatchId($latestBatch->id());

        // Analiza częstości liczb w wygenerowanych tipach
        $numberFrequency = $this->calculateNumberFrequency($combinations);

        // Analiza zaległych liczb (due numbers) z bazy
        $clickhouse = ClickHouseConnection::create();
        $dueNumbers = $this->fetchDueNumbers($clickhouse);

        // Znajdź zaległe liczby, które są w tipach
        $dueNumbersInTips = $this->findDueNumbersInTips($dueNumbers, $numberFrequency);

        // Najczęściej występujące liczby historycznie
        $hotNumbers = $this->fetchHotNumbers($clickhouse);

        // Znajdź gorące liczby, które są w tipach
        $hotNumbersInTips = $this->findHotNumbersInTips($hotNumbers, $numberFrequency);

        // Inteligentny TOP 7 uwzględniający wszystkie czynniki
        $top7Numbers = $this->calculateIntelligentTop7(
            $clickhouse,
            $numberFrequency,
            $dueNumbers,
            $hotNumbers
        );

        return $this->twig->render('tips/index.html.twig', [
            'batch' => $latestBatch,
            'combinations' => $combinations,
            'number_frequency' => $numberFrequency,
            'due_numbers' => $dueNumbers,
            'due_numbers_in_tips' => $dueNumbersInTips,
            'hot_numbers' => $hotNumbers,
            'hot_numbers_in_tips' => $hotNumbersInTips,
            'top7_numbers' => $top7Numbers,
            'user_email' => $this->session->get('user_email'),
            'flashes' => $flashes,
        ]);
    }

    /**
     * Oblicza częstość występowania liczb w wygenerowanych tipach
     *
     * @param array<TipCombination> $combinations
     * @return array<int, int> Tablica [liczba => liczba_wystąpień] posortowana malejąco
     */
    private function calculateNumberFrequency(array $combinations): array
    {
        $frequency = [];

        foreach ($combinations as $combination) {
            // Parsuj combo (format: "01-24-47-62-75")
            $numbers = explode('-', $combination->combo());
            foreach ($numbers as $number) {
                $num = (int) $number;
                if (!isset($frequency[$num])) {
                    $frequency[$num] = 0;
                }
                $frequency[$num]++;
            }
        }

        // Sortuj malejąco po częstości
        arsort($frequency);

        return $frequency;
    }

    /**
     * Znajduje zaległe liczby, które występują w wygenerowanych tipach
     *
     * @param array<array{number: int, days_since_last: int, last_date: string}> $dueNumbers
     * @param array<int, int> $numberFrequency
     * @return array<array{number: int, days_since_last: int, last_date: string, tip_count: int}>
     */
    private function findDueNumbersInTips(array $dueNumbers, array $numberFrequency): array
    {
        $dueInTips = [];

        foreach ($dueNumbers as $dueNumber) {
            $number = $dueNumber['number'];
            if (isset($numberFrequency[$number])) {
                $dueInTips[] = [
                    'number' => $number,
                    'days_since_last' => $dueNumber['days_since_last'],
                    'last_date' => $dueNumber['last_date'],
                    'tip_count' => $numberFrequency[$number],
                ];
            }
        }

        // Sortuj po liczbie wystąpień w tipach (malejąco)
        usort($dueInTips, fn($a, $b) => $b['tip_count'] <=> $a['tip_count']);

        return $dueInTips;
    }

    /**
     * Pobiera najczęściej występujące liczby historycznie (hot numbers)
     *
     * @return array<array{number: int, total_count: int, draw_count: int}> Top 20 gorących liczb
     */
    private function fetchHotNumbers(\ClickHouseDB\Client $clickhouse): array
    {
        // Zapytanie znajduje dla każdej liczby (1-80) ile razy wystąpiła w losowaniach
        $result = $clickhouse->select(
            <<<SQL
            SELECT
                arrayJoin(numbers) as number,
                count() as draw_count
            FROM analytics.draws
            GROUP BY number
            ORDER BY draw_count DESC
            LIMIT 20
            SQL
        );

        $hotNumbers = [];
        foreach ($result->rows() as $row) {
            $hotNumbers[] = [
                'number' => (int) $row['number'],
                'draw_count' => (int) $row['draw_count'],
            ];
        }

        return $hotNumbers;
    }

    /**
     * Znajduje gorące liczby, które występują w wygenerowanych tipach
     *
     * @param array<array{number: int, draw_count: int}> $hotNumbers
     * @param array<int, int> $numberFrequency
     * @return array<array{number: int, draw_count: int, tip_count: int}>
     */
    private function findHotNumbersInTips(array $hotNumbers, array $numberFrequency): array
    {
        $hotInTips = [];

        foreach ($hotNumbers as $hotNumber) {
            $number = $hotNumber['number'];
            if (isset($numberFrequency[$number])) {
                $hotInTips[] = [
                    'number' => $number,
                    'draw_count' => $hotNumber['draw_count'],
                    'tip_count' => $numberFrequency[$number],
                ];
            }
        }

        // Sortuj po liczbie wystąpień w tipach (malejąco)
        usort($hotInTips, fn($a, $b) => $b['tip_count'] <=> $a['tip_count']);

        return $hotInTips;
    }

    /**
     * Pobiera liczby z największą przerwą od ostatniego wystąpienia (due numbers)
     *
     * @return array<array{number: int, days_since_last: int, last_date: string}> Top 20 zaległych liczb
     */
    private function fetchDueNumbers(\ClickHouseDB\Client $clickhouse): array
    {
        // Zapytanie znajduje dla każdej liczby (1-80) datę ostatniego wystąpienia
        // i oblicza ile dni minęło od tego czasu
        $result = $clickhouse->select(
            <<<SQL
            WITH number_last_dates AS (
                SELECT
                    arrayJoin(numbers) as number,
                    max(draw_date) as last_date
                FROM analytics.draws
                GROUP BY number
            )
            SELECT
                number,
                last_date,
                dateDiff('day', last_date, today()) as days_since_last
            FROM number_last_dates
            ORDER BY days_since_last DESC
            LIMIT 20
            SQL
        );

        $dueNumbers = [];
        foreach ($result->rows() as $row) {
            $dueNumbers[] = [
                'number' => (int) $row['number'],
                'days_since_last' => (int) $row['days_since_last'],
                'last_date' => $row['last_date'],
            ];
        }

        return $dueNumbers;
    }

    /**
     * Oblicza inteligentny TOP 7 liczb uwzględniający wszystkie czynniki
     *
     * @param array<int, int> $numberFrequency Częstość liczb w tipach
     * @param array<array{number: int, days_since_last: int, last_date: string}> $dueNumbers
     * @param array<array{number: int, draw_count: int}> $hotNumbers
     * @return array<array{number: int, total_score: float, breakdown: array, reason: string}>
     */
    private function calculateIntelligentTop7(
        \ClickHouseDB\Client $clickhouse,
        array $numberFrequency,
        array $dueNumbers,
        array $hotNumbers
    ): array {
        // 1. Pobierz dane dla wszystkich liczb 1-80
        $allNumbersData = $this->fetchAllNumbersData($clickhouse);

        // 2. Pobierz najpopularniejsze pary i trójki
        $topPairs = $this->fetchTopPairs($clickhouse, 50);
        $topTriples = $this->fetchTopTriples($clickhouse, 30);

        // 3. Pobierz liczby które powinny paść w bieżącym miesiącu
        $monthlyExpected = $this->fetchMonthlyExpectedNumbers($clickhouse);

        // 4. Przygotuj mapy do szybkiego dostępu
        $dueMap = [];
        foreach ($dueNumbers as $due) {
            $dueMap[$due['number']] = $due['days_since_last'];
        }

        $hotMap = [];
        foreach ($hotNumbers as $hot) {
            $hotMap[$hot['number']] = $hot['draw_count'];
        }

        // 5. Oblicz score dla każdej liczby
        $scores = [];
        for ($number = 1; $number <= 80; $number++) {
            $tipFreq = $numberFrequency[$number] ?? 0;
            $dueScore = $this->calculateDueScore($number, $dueMap, $allNumbersData);
            $hotScore = $this->calculateHotScore($number, $hotMap, $allNumbersData);
            $monthlyScore = $this->calculateMonthlyScore($number, $monthlyExpected, $allNumbersData);
            $pairTripleBonus = $this->calculatePairTripleBonus($number, $topPairs, $topTriples);
            $randomness = (mt_rand(-50, 50) / 10); // ±5.0

            $totalScore = $tipFreq * 0.30
                + $dueScore * 0.25
                + $hotScore * 0.20
                + $monthlyScore * 0.15
                + $pairTripleBonus * 0.10
                + $randomness;

            $reason = $this->generateReason(
                $number,
                $tipFreq,
                $dueScore,
                $hotScore,
                $monthlyScore,
                $pairTripleBonus,
                $dueMap,
                $hotMap
            );

            $scores[] = [
                'number' => $number,
                'total_score' => round($totalScore, 2),
                'breakdown' => [
                    'tip_frequency' => round($tipFreq * 0.30, 2),
                    'due_score' => round($dueScore * 0.25, 2),
                    'hot_score' => round($hotScore * 0.20, 2),
                    'monthly_pattern' => round($monthlyScore * 0.15, 2),
                    'pair_triple_bonus' => round($pairTripleBonus * 0.10, 2),
                    'randomness' => round($randomness, 2),
                ],
                'reason' => $reason,
            ];
        }

        // 6. Sortuj i weź top 7
        usort($scores, fn($a, $b) => $b['total_score'] <=> $a['total_score']);

        return array_slice($scores, 0, 7);
    }

    private function fetchAllNumbersData(\ClickHouseDB\Client $clickhouse): array
    {
        $result = $clickhouse->select(
            <<<SQL
            SELECT
                arrayJoin(numbers) as number,
                count() as total_draws,
                max(draw_date) as last_date,
                dateDiff('day', max(draw_date), today()) as days_since_last
            FROM analytics.draws
            GROUP BY number
            SQL
        );

        $data = [];
        foreach ($result->rows() as $row) {
            $data[(int) $row['number']] = [
                'total_draws' => (int) $row['total_draws'],
                'last_date' => $row['last_date'],
                'days_since_last' => (int) $row['days_since_last'],
            ];
        }

        return $data;
    }

    private function fetchTopPairs(\ClickHouseDB\Client $clickhouse, int $limit): array
    {
        // Uproszczone zapytanie - zwraca top liczby jako proxy dla par
        // Pełna analiza par byłaby zbyt kosztowna (16k * 190 kombinacji par)
        $result = $clickhouse->select(
            "
            SELECT
                arrayJoin(numbers) as number,
                count() as frequency
            FROM analytics.draws
            GROUP BY number
            ORDER BY frequency DESC
            LIMIT {$limit}
            "
        );

        $pairs = [];
        foreach ($result->rows() as $row) {
            $pairs[] = [
                'pair' => [(int) $row['number'], (int) $row['number']], // Uproszczenie
                'frequency' => (int) $row['frequency'],
            ];
        }

        return $pairs;
    }

    private function fetchTopTriples(\ClickHouseDB\Client $clickhouse, int $limit): array
    {
        // Uproszczone zapytanie - zwraca top liczby jako proxy dla trójek
        // Pełna analiza trójek byłaby zbyt kosztowna (16k * 1140 kombinacji trójek)
        $result = $clickhouse->select(
            "
            SELECT
                arrayJoin(numbers) as number,
                count() as frequency
            FROM analytics.draws
            GROUP BY number
            ORDER BY frequency DESC
            LIMIT {$limit}
            "
        );

        $triples = [];
        foreach ($result->rows() as $row) {
            $triples[] = [
                'triple' => [(int) $row['number'], (int) $row['number'], (int) $row['number']], // Uproszczenie
                'frequency' => (int) $row['frequency'],
            ];
        }

        return $triples;
    }

    private function fetchMonthlyExpectedNumbers(\ClickHouseDB\Client $clickhouse): array
    {
        $currentMonth = (int) date('m');

        $result = $clickhouse->select(
            "
            WITH monthly_freq AS (
                SELECT
                    arrayJoin(numbers) as number,
                    toMonth(draw_date) as month,
                    count() as appearances
                FROM analytics.draws
                GROUP BY number, month
            ),
            avg_monthly AS (
                SELECT
                    number,
                    month,
                    appearances,
                    avg(appearances) OVER (PARTITION BY number) as avg_for_number
                FROM monthly_freq
            )
            SELECT
                number,
                appearances as this_month_historical_avg,
                avg_for_number as overall_avg
            FROM avg_monthly
            WHERE month = {$currentMonth}
            ORDER BY appearances DESC
            "
        );

        $expected = [];
        foreach ($result->rows() as $row) {
            $expected[(int) $row['number']] = [
                'this_month_avg' => (float) $row['this_month_historical_avg'],
                'overall_avg' => (float) $row['overall_avg'],
            ];
        }

        return $expected;
    }

    private function calculateDueScore(int $number, array $dueMap, array $allNumbersData): float
    {
        if (!isset($allNumbersData[$number])) {
            return 0.0;
        }

        $daysSince = $allNumbersData[$number]['days_since_last'];

        // Normalizuj do 0-100 (zakładamy max 365 dni)
        return min(100, ($daysSince / 365) * 100);
    }

    private function calculateHotScore(int $number, array $hotMap, array $allNumbersData): float
    {
        if (!isset($allNumbersData[$number])) {
            return 0.0;
        }

        $totalDraws = $allNumbersData[$number]['total_draws'];

        // Normalizuj do 0-100 (zakładamy max 3500 wystąpień dla 16k losowań)
        return min(100, ($totalDraws / 3500) * 100);
    }

    private function calculateMonthlyScore(int $number, array $monthlyExpected, array $allNumbersData): float
    {
        if (!isset($monthlyExpected[$number]) || !isset($allNumbersData[$number])) {
            return 0.0;
        }

        $thisMonthAvg = $monthlyExpected[$number]['this_month_avg'];
        $overallAvg = $monthlyExpected[$number]['overall_avg'];
        $daysSince = $allNumbersData[$number]['days_since_last'];

        // Jeśli liczba ma wysoką średnią w tym miesiącu, ale dawno nie padła
        if ($thisMonthAvg > $overallAvg && $daysSince > 30) {
            return 100;
        }

        if ($thisMonthAvg > $overallAvg) {
            return 60;
        }

        return 20;
    }

    private function calculatePairTripleBonus(int $number, array $topPairs, array $topTriples): float
    {
        $bonus = 0;

        // Sprawdź czy liczba jest w top parach
        foreach ($topPairs as $pairData) {
            if (in_array($number, $pairData['pair'], true)) {
                $bonus += 5;
            }
        }

        // Sprawdź czy liczba jest w top trójkach
        foreach ($topTriples as $tripleData) {
            if (in_array($number, $tripleData['triple'], true)) {
                $bonus += 10;
            }
        }

        return min(100, $bonus);
    }

    private function generateReason(
        int $number,
        float $tipFreq,
        float $dueScore,
        float $hotScore,
        float $monthlyScore,
        float $pairTripleBonus,
        array $dueMap,
        array $hotMap
    ): string {
        $reasons = [];

        if ($tipFreq > 5) {
            $reasons[] = sprintf('Często w tipach (%d)', (int) $tipFreq);
        }

        if (isset($dueMap[$number]) && $dueMap[$number] > 60) {
            $reasons[] = sprintf('Zaległe (%d dni)', $dueMap[$number]);
        }

        if (isset($hotMap[$number]) && $hotMap[$number] > 2800) {
            $reasons[] = sprintf('Gorące (%d wystąpień)', $hotMap[$number]);
        }

        if ($monthlyScore > 50) {
            $reasons[] = 'Powinno paść w tym miesiącu';
        }

        if ($pairTripleBonus > 20) {
            $reasons[] = 'Silne pary/trójki';
        }

        return empty($reasons) ? 'Zrównoważony profil' : implode(', ', $reasons);
    }

    private function getLastSuccessfulImportJobId(): ?int
    {
        $result = $this->connection->fetchAssociative(
            "SELECT id FROM import_jobs WHERE status = 'succeeded' ORDER BY finished_at DESC LIMIT 1"
        );

        return $result !== false ? (int) $result['id'] : null;
    }
}
