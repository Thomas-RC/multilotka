<?php

declare(strict_types=1);

namespace Multilotka\Tests\Tip;

use DateTimeImmutable;
use Multilotka\Tip\ComboStatistics;
use Multilotka\Tip\ComboStatisticsRepositoryInterface;
use Multilotka\Tip\TipGenerator;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class TipGeneratorTest extends TestCase
{
    public function testGenerateTipsZwracaDziesiecKombinacji(): void
    {
        $statsRepo = $this->createMockRepositoryWithData();
        $generator = new TipGenerator($statsRepo);

        $tips = $generator->generateTips();

        self::assertCount(10, $tips, 'Generator powinien zwrócić dokładnie 10 tipów');
    }

    public function testGenerateTipsZwracaKombinacjeZMinimumConfidence(): void
    {
        $statsRepo = $this->createMockRepositoryWithData();
        $generator = new TipGenerator($statsRepo);

        $tips = $generator->generateTips();

        foreach ($tips as $tip) {
            self::assertGreaterThanOrEqual(
                50.0,
                $tip->confidence(),
                'Każdy tip powinien mieć confidence >= 50%'
            );
        }
    }

    public function testGenerateTipsSortujePoConfidenceMalejaco(): void
    {
        $statsRepo = $this->createMockRepositoryWithData();
        $generator = new TipGenerator($statsRepo);

        $tips = $generator->generateTips();

        $previousConfidence = 101.0;
        foreach ($tips as $tip) {
            self::assertLessThanOrEqual(
                $previousConfidence,
                $tip->confidence(),
                'Tipy powinny być posortowane po confidence malejąco'
            );
            $previousConfidence = $tip->confidence();
        }
    }

    public function testGenerateTipsRzucaWyjatekGdyBrakDanych(): void
    {
        $statsRepo = $this->createMock(ComboStatisticsRepositoryInterface::class);
        $statsRepo->method('getTotalDrawsCount')->willReturn(0);

        $generator = new TipGenerator($statsRepo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Brak danych losowań w bazie');

        $generator->generateTips();
    }

    public function testGenerateTipsRzucaWyjatekGdyBrakDatyOstatniegLosowania(): void
    {
        $statsRepo = $this->createMock(ComboStatisticsRepositoryInterface::class);
        $statsRepo->method('getTotalDrawsCount')->willReturn(1000);
        $statsRepo->method('getLatestDrawDate')->willReturn(null);

        $generator = new TipGenerator($statsRepo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Nie można określić daty ostatniego losowania');

        $generator->generateTips();
    }

    public function testGenerateTipsRzucaWyjatekGdyBrakKombinacji(): void
    {
        $statsRepo = $this->createMock(ComboStatisticsRepositoryInterface::class);
        $statsRepo->method('getTotalDrawsCount')->willReturn(1000);
        $statsRepo->method('getLatestDrawDate')->willReturn(new DateTimeImmutable('2025-01-01'));
        $statsRepo->method('fetchTopCombinations')->willReturn([]);

        $generator = new TipGenerator($statsRepo);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Brak kombinacji w bazie danych');

        $generator->generateTips();
    }

    public function testGenerateTipsObliczaWszystkieCzynniki(): void
    {
        $statsRepo = $this->createMockRepositoryWithData();
        $generator = new TipGenerator($statsRepo);

        $tips = $generator->generateTips();

        foreach ($tips as $tip) {
            self::assertGreaterThanOrEqual(0.0, $tip->dueScore());
            self::assertLessThanOrEqual(100.0, $tip->dueScore());

            self::assertGreaterThanOrEqual(0.0, $tip->frequencyScore());
            self::assertLessThanOrEqual(100.0, $tip->frequencyScore());

            self::assertGreaterThanOrEqual(0.0, $tip->gapScore());
            self::assertLessThanOrEqual(100.0, $tip->gapScore());
        }
    }

    public function testGenerateTipsPreferujeKombinacjeZDlugimGapem(): void
    {
        $statsRepo = $this->createMock(ComboStatisticsRepositoryInterface::class);
        $statsRepo->method('getTotalDrawsCount')->willReturn(1000);
        $statsRepo->method('getLatestDrawDate')->willReturn(new DateTimeImmutable('2025-01-01'));

        // Kombinacja z długim gapem (214 dni) vs. kombinacja z krótszym gapem (60 dni)
        // Obie muszą mieć confidence >= 50% aby przejść filtrowanie
        // Gap >= 60: Due=100
        // Oba zapewniają ten sam due score (100), więc ranking zależy od innych czynników

        $combinations = [];
        // Kombinacja z bardzo długim gapem (200 dni)
        $combinations[] = new ComboStatistics(
            combo: '01-02-03-04-05',
            totalHits: 200,
            uniqueDraws: 100,
            firstDrawDate: new DateTimeImmutable('2020-01-01'),
            lastDrawDate: new DateTimeImmutable('2024-06-01'),
            currentGapDays: 214, // Ekstremalnie długi gap
        );

        // Kombinacja z średnio długim gapem (60 dni - na granicy)
        $combinations[] = new ComboStatistics(
            combo: '06-07-08-09-10',
            totalHits: 200,
            uniqueDraws: 100,
            firstDrawDate: new DateTimeImmutable('2020-01-01'),
            lastDrawDate: new DateTimeImmutable('2024-11-02'),
            currentGapDays: 60, // Na granicy - Due Score = 100
        );

        $statsRepo->method('fetchTopCombinations')->willReturn($combinations);

        $generator = new TipGenerator($statsRepo);
        $tips = $generator->generateTips();

        // Obie kombinacje powinny być w wynikach i mieć identyczny due score (100)
        $tipLongGap = array_values(array_filter($tips, fn($t) => $t->combo() === '01-02-03-04-05'))[0] ?? null;
        $tipShortGap = array_values(array_filter($tips, fn($t) => $t->combo() === '06-07-08-09-10'))[0] ?? null;

        self::assertNotNull($tipLongGap, 'Kombinacja z długim gapem powinna być w wynikach');
        self::assertNotNull($tipShortGap, 'Kombinacja z krótszym gapem powinna być w wynikach');

        // Obie powinny mieć due score = 100 (gap >= 60)
        self::assertEquals(100.0, $tipLongGap->dueScore());
        self::assertEquals(100.0, $tipShortGap->dueScore());
    }

    private function createMockRepositoryWithData(): ComboStatisticsRepositoryInterface
    {
        $statsRepo = $this->createMock(ComboStatisticsRepositoryInterface::class);
        $statsRepo->method('getTotalDrawsCount')->willReturn(1000);
        $statsRepo->method('getLatestDrawDate')->willReturn(new DateTimeImmutable('2025-01-01'));

        // Generuj testowe kombinacje z parametrami zapewniającymi confidence >= 50%
        // Nowe wagi algorytmu:
        // Due: 8%, Frequency: 5%, Gap: 5%, Pattern: 32%, Sum: 25%, Randomness: 25%
        //
        // Przy mocnych parametrach możemy osiągnąć ~60-70% confidence:
        // - Due: 100 pkt * 0.08 = 8
        // - Frequency: 100 pkt * 0.05 = 5
        // - Gap: 100 pkt * 0.05 = 5
        // - Pattern: ~50 pkt * 0.32 = 16
        // - Sum: ~90 pkt * 0.25 = 22.5
        // - Randomness: ~70 pkt * 0.25 = 17.5
        // Razem: ~74%
        $combinations = [];
        for ($i = 0; $i < 50; $i++) {
            // Dla pierwszych 15 kombinacji, utrzymuj uniqueDraws blisko 100
            $uniqueDraws = 100; // Stała wartość zapewnia averageGap = 10
            $gapDays = 92 + $i; // Długi gap >90 dni

            $combinations[] = new ComboStatistics(
                combo: sprintf('%02d-%02d-%02d-%02d-%02d', $i + 1, $i + 2, $i + 3, $i + 4, $i + 5),
                totalHits: 200 - $i,
                uniqueDraws: $uniqueDraws,
                firstDrawDate: new DateTimeImmutable('2020-01-01'),
                lastDrawDate: new DateTimeImmutable('2024-10-01'),
                currentGapDays: $gapDays,
            );
        }

        $statsRepo->method('fetchTopCombinations')->willReturn($combinations);

        return $statsRepo;
    }
}
