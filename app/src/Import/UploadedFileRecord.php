<?php

declare(strict_types=1);

namespace Multilotka\Import;

use DateTimeImmutable;

final class UploadedFileRecord
{
    public function __construct(
        private ?int $id,
        private readonly ?int $userId,
        private readonly string $originalName,
        private readonly string $storedPath,
        private readonly string $sha256,
        private readonly int $rowsTotal,
        private readonly ?DateTimeImmutable $drawDateStart,
        private readonly ?DateTimeImmutable $drawDateEnd,
        private string $status,
        private readonly DateTimeImmutable $uploadedAt,
    ) {
    }

    public static function validated(
        ?int $userId,
        string $originalName,
        string $storedPath,
        string $sha256,
        UploadedFileMetadata $metadata,
        DateTimeImmutable $uploadedAt,
    ): self {
        return new self(
            null,
            $userId,
            $originalName,
            $storedPath,
            $sha256,
            $metadata->rowsTotal(),
            $metadata->drawDateStart(),
            $metadata->drawDateEnd(),
            'validated',
            $uploadedAt,
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromDatabaseRow(array $row): self
    {
        return new self(
            isset($row['id']) ? (int) $row['id'] : null,
            isset($row['user_id']) && $row['user_id'] !== null ? (int) $row['user_id'] : null,
            (string) $row['original_name'],
            (string) $row['stored_path'],
            (string) $row['sha256'],
            (int) $row['rows_total'],
            isset($row['draw_date_start']) && $row['draw_date_start'] !== null
                ? new DateTimeImmutable((string) $row['draw_date_start'])
                : null,
            isset($row['draw_date_end']) && $row['draw_date_end'] !== null
                ? new DateTimeImmutable((string) $row['draw_date_end'])
                : null,
            (string) $row['status'],
            isset($row['uploaded_at']) && $row['uploaded_at'] !== null
                ? new DateTimeImmutable((string) $row['uploaded_at'])
                : new DateTimeImmutable('now'),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function withId(int $id): self
    {
        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }

    public function userId(): ?int
    {
        return $this->userId;
    }

    public function originalName(): string
    {
        return $this->originalName;
    }

    public function storedPath(): string
    {
        return $this->storedPath;
    }

    public function sha256(): string
    {
        return $this->sha256;
    }

    public function rowsTotal(): int
    {
        return $this->rowsTotal;
    }

    public function drawDateStart(): ?DateTimeImmutable
    {
        return $this->drawDateStart;
    }

    public function drawDateEnd(): ?DateTimeImmutable
    {
        return $this->drawDateEnd;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function uploadedAt(): DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function markArchived(): self
    {
        $clone = clone $this;
        $clone->status = 'archived';

        return $clone;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabasePayload(): array
    {
        return [
            'user_id' => $this->userId,
            'original_name' => $this->originalName,
            'stored_path' => $this->storedPath,
            'sha256' => $this->sha256,
            'rows_total' => $this->rowsTotal,
            'draw_date_start' => $this->drawDateStart?->format('Y-m-d'),
            'draw_date_end' => $this->drawDateEnd?->format('Y-m-d'),
            'status' => $this->status,
            'uploaded_at' => $this->uploadedAt->format('Y-m-d H:i:s'),
        ];
    }
}
