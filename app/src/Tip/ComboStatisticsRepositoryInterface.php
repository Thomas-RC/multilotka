<?php

declare(strict_types=1);

namespace Multilotka\Tip;

use DateTimeImmutable;

interface ComboStatisticsRepositoryInterface
{
    /**
     * Pobiera statystyki kombinacji z ClickHouse
     *
     * @param int $limit Maksymalna liczba kombinacji do pobrania
     * @return array<ComboStatistics>
     */
    public function fetchTopCombinations(int $limit = 1000): array;

    /**
     * Pobiera całkowitą liczbę losowań w bazie
     */
    public function getTotalDrawsCount(): int;

    /**
     * Pobiera najnowszą datę losowania
     */
    public function getLatestDrawDate(): ?DateTimeImmutable;
}
