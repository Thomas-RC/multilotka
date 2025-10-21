<?php

declare(strict_types=1);

namespace Multilotka\Tip;

use DateTimeImmutable;

final class TipCombination
{
    private function __construct(
        private readonly ?int $id,
        private readonly int $tipBatchId,
        private readonly int $sequence,
        private readonly string $combo,
        private readonly float $confidence,
        private readonly float $dueScore,
        private readonly float $frequencyScore,
        private readonly float $gapScore,
    ) {
    }

    public static function create(
        int $tipBatchId,
        int $sequence,
        string $combo,
        float $confidence,
        float $dueScore,
        float $frequencyScore,
        float $gapScore,
    ): self {
        return new self(
            null,
            $tipBatchId,
            $sequence,
            $combo,
            $confidence,
            $dueScore,
            $frequencyScore,
            $gapScore,
        );
    }

    public static function fromDatabase(
        int $id,
        int $tipBatchId,
        int $sequence,
        string $combo,
        float $confidence,
        float $dueScore,
        float $frequencyScore,
        float $gapScore,
    ): self {
        return new self(
            $id,
            $tipBatchId,
            $sequence,
            $combo,
            $confidence,
            $dueScore,
            $frequencyScore,
            $gapScore,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function tipBatchId(): int
    {
        return $this->tipBatchId;
    }

    public function sequence(): int
    {
        return $this->sequence;
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
