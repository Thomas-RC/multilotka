<?php

declare(strict_types=1);

namespace Multilotka\Tip;

use DateTimeImmutable;

/**
 * Value object reprezentujący statystyki kombinacji z ClickHouse
 */
final class ComboStatistics
{
    public function __construct(
        private readonly string $combo,
        private readonly int $totalHits,
        private readonly int $uniqueDraws,
        private readonly DateTimeImmutable $firstDrawDate,
        private readonly DateTimeImmutable $lastDrawDate,
        private readonly int $currentGapDays,
    ) {
    }

    public function combo(): string
    {
        return $this->combo;
    }

    public function totalHits(): int
    {
        return $this->totalHits;
    }

    public function uniqueDraws(): int
    {
        return $this->uniqueDraws;
    }

    public function firstDrawDate(): DateTimeImmutable
    {
        return $this->firstDrawDate;
    }

    public function lastDrawDate(): DateTimeImmutable
    {
        return $this->lastDrawDate;
    }

    public function currentGapDays(): int
    {
        return $this->currentGapDays;
    }

    /**
     * @return array<int>
     */
    public function numbersArray(): array
    {
        return array_map('intval', explode('-', $this->combo));
    }
}
