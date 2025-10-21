<?php

declare(strict_types=1);

namespace Multilotka\Stats;

use DateTimeImmutable;

final readonly class TopCombo
{
    public function __construct(
        public string $combo,
        public int $totalHits,
        public int $uniqueDraws,
        public DateTimeImmutable $firstDrawDate,
        public DateTimeImmutable $lastDrawDate,
    ) {
    }

    /**
     * @return array<int>
     */
    public function numbersArray(): array
    {
        return array_map('intval', explode('-', $this->combo));
    }
}
