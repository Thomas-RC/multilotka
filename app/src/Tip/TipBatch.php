<?php

declare(strict_types=1);

namespace Multilotka\Tip;

use DateTimeImmutable;

final class TipBatch
{
    private function __construct(
        private readonly ?int $id,
        private readonly ?int $importJobId,
        private readonly int $userId,
        private readonly string $status,
        private readonly float $confidenceFloor,
        private readonly ?float $averageConfidence,
        private readonly DateTimeImmutable $generatedAt,
        private readonly ?string $notes,
    ) {
    }

    public static function create(
        int $userId,
        ?int $importJobId,
        float $confidenceFloor,
        DateTimeImmutable $generatedAt,
        ?string $notes = null,
    ): self {
        return new self(
            null,
            $importJobId,
            $userId,
            'draft',
            $confidenceFloor,
            null,
            $generatedAt,
            $notes,
        );
    }

    public static function fromDatabase(
        int $id,
        ?int $importJobId,
        int $userId,
        string $status,
        float $confidenceFloor,
        ?float $averageConfidence,
        DateTimeImmutable $generatedAt,
        ?string $notes,
    ): self {
        return new self(
            $id,
            $importJobId,
            $userId,
            $status,
            $confidenceFloor,
            $averageConfidence,
            $generatedAt,
            $notes,
        );
    }

    public function withId(int $id): self
    {
        return new self(
            $id,
            $this->importJobId,
            $this->userId,
            $this->status,
            $this->confidenceFloor,
            $this->averageConfidence,
            $this->generatedAt,
            $this->notes,
        );
    }

    public function withAverageConfidence(float $averageConfidence): self
    {
        return new self(
            $this->id,
            $this->importJobId,
            $this->userId,
            $this->status,
            $this->confidenceFloor,
            $averageConfidence,
            $this->generatedAt,
            $this->notes,
        );
    }

    public function publish(): self
    {
        return new self(
            $this->id,
            $this->importJobId,
            $this->userId,
            'published',
            $this->confidenceFloor,
            $this->averageConfidence,
            $this->generatedAt,
            $this->notes,
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function importJobId(): ?int
    {
        return $this->importJobId;
    }

    public function userId(): int
    {
        return $this->userId;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function confidenceFloor(): float
    {
        return $this->confidenceFloor;
    }

    public function averageConfidence(): ?float
    {
        return $this->averageConfidence;
    }

    public function generatedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }
}
