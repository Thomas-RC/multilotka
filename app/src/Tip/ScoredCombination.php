<?php

declare(strict_types=1);

namespace Multilotka\Tip;

/**
 * Value object reprezentujący kombinację z obliczonymi wynikami
 */
final class ScoredCombination
{
    public function __construct(
        private readonly string $combo,
        private readonly float $confidence,
        private readonly float $dueScore,
        private readonly float $frequencyScore,
        private readonly float $gapScore,
    ) {
    }

    public function combo(): string
    {
        return $this->combo;
    }

    public function confidence(): float
    {
        return $this->confidence;
    }

    public function dueScore(): float
    {
        return $this->dueScore;
    }

    public function frequencyScore(): float
    {
        return $this->frequencyScore;
    }

    public function gapScore(): float
    {
        return $this->gapScore;
    }

    /**
     * @return array<int>
     */
    public function numbersArray(): array
    {
        return array_map('intval', explode('-', $this->combo));
    }
}
