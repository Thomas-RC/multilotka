<?php

declare(strict_types=1);

namespace Multilotka\Stats;

use DateTimeImmutable;

final readonly class MonthlyStats
{
    public function __construct(
        public DateTimeImmutable $month,
        public int $hits,
    ) {
    }
}
