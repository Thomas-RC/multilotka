<?php

declare(strict_types=1);

namespace Multilotka\Import;

use DateTimeImmutable;
use JsonException;

final class ImportJobEvent
{
    /**
     * @param array<string, mixed>|null $payload
     */
    private function __construct(
        private ?int $id,
        private readonly int $jobId,
        private ImportJobEventType $type,
        private ?array $payload,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    public static function create(
        int $jobId,
        ImportJobEventType $type,
        ?array $payload,
        DateTimeImmutable $createdAt,
    ): self {
        return new self(
            id: null,
            jobId: $jobId,
            type: $type,
            payload: $payload,
            createdAt: $createdAt,
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws JsonException
     */
    public static function fromDatabaseRow(array $row): self
    {
        $payload = isset($row['payload']) && $row['payload'] !== null
            ? json_decode((string) $row['payload'], true, 512, JSON_THROW_ON_ERROR)
            : null;

        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            jobId: (int) $row['import_job_id'],
            type: ImportJobEventType::from((string) $row['event_type']),
            payload: $payload,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
        );
    }

    public function id(): ?int
    {
        return $this->id;
    }

    public function jobId(): int
    {
        return $this->jobId;
    }

    public function type(): ImportJobEventType
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function payload(): ?array
    {
        return $this->payload;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabasePayload(): array
    {
        return [
            'import_job_id' => $this->jobId,
            'event_type' => $this->type->value,
            'payload' => $this->payload !== null
                ? json_encode($this->payload, JSON_THROW_ON_ERROR)
                : null,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    public function withId(int $id): self
    {
        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }
}
