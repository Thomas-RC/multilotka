<?php

declare(strict_types=1);

namespace Multilotka\Import;

use DateTimeImmutable;
use JsonException;

final class ImportJob
{
    /**
     * @param array<string, mixed> $etlRequestPayload
     * @param array<string, mixed>|null $etlResponsePayload
     */
    private function __construct(
        private ?int $id,
        private readonly int $fileId,
        private ImportMode $mode,
        private ImportStatus $status,
        private int $progressPercent,
        private ?string $currentStage,
        private ?int $drawsProcessed,
        private ?int $combinationsInserted,
        private ?DateTimeImmutable $startedAt,
        private ?DateTimeImmutable $finishedAt,
        private ?int $durationSeconds,
        private array $etlRequestPayload,
        private ?array $etlResponsePayload,
        private ?string $errorMessage,
        private readonly DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param array<string, mixed> $etlRequestPayload
     */
    public static function queue(
        int $fileId,
        ImportMode $mode,
        array $etlRequestPayload,
        DateTimeImmutable $now,
    ): self {
        return new self(
            id: null,
            fileId: $fileId,
            mode: $mode,
            status: ImportStatus::QUEUED,
            progressPercent: 0,
            currentStage: null,
            drawsProcessed: null,
            combinationsInserted: null,
            startedAt: null,
            finishedAt: null,
            durationSeconds: null,
            etlRequestPayload: $etlRequestPayload,
            etlResponsePayload: null,
            errorMessage: null,
            createdAt: $now,
        );
    }

    /**
     * @param array<string, mixed> $row
     *
     * @throws JsonException
     */
    public static function fromDatabaseRow(array $row): self
    {
        $requestPayload = isset($row['etl_request_payload']) && $row['etl_request_payload'] !== null
            ? json_decode((string) $row['etl_request_payload'], true, 512, JSON_THROW_ON_ERROR)
            : [];

        $responsePayload = isset($row['etl_response_payload']) && $row['etl_response_payload'] !== null
            ? json_decode((string) $row['etl_response_payload'], true, 512, JSON_THROW_ON_ERROR)
            : null;

        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            fileId: (int) $row['file_id'],
            mode: ImportMode::from((string) $row['mode']),
            status: ImportStatus::from((string) $row['status']),
            progressPercent: isset($row['progress_percent']) ? (int) $row['progress_percent'] : 0,
            currentStage: $row['current_stage'] !== null ? (string) $row['current_stage'] : null,
            drawsProcessed: $row['draws_processed'] !== null ? (int) $row['draws_processed'] : null,
            combinationsInserted: $row['combinations_inserted'] !== null ? (int) $row['combinations_inserted'] : null,
            startedAt: $row['started_at'] !== null ? new DateTimeImmutable((string) $row['started_at']) : null,
            finishedAt: $row['finished_at'] !== null ? new DateTimeImmutable((string) $row['finished_at']) : null,
            durationSeconds: $row['duration_seconds'] !== null ? (int) $row['duration_seconds'] : null,
            etlRequestPayload: $requestPayload,
            etlResponsePayload: $responsePayload,
            errorMessage: $row['error_message'] !== null ? (string) $row['error_message'] : null,
            createdAt: new DateTimeImmutable((string) $row['created_at']),
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

    public function fileId(): int
    {
        return $this->fileId;
    }

    public function mode(): ImportMode
    {
        return $this->mode;
    }

    public function status(): ImportStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function etlRequestPayload(): array
    {
        return $this->etlRequestPayload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function etlResponsePayload(): ?array
    {
        return $this->etlResponsePayload;
    }

    public function progressPercent(): int
    {
        return $this->progressPercent;
    }

    public function currentStage(): ?string
    {
        return $this->currentStage;
    }

    public function startedAt(): ?DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function errorMessage(): ?string
    {
        return $this->errorMessage;
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabasePayload(): array
    {
        return [
            'file_id' => $this->fileId,
            'mode' => $this->mode->value,
            'status' => $this->status->value,
            'progress_percent' => $this->progressPercent,
            'current_stage' => $this->currentStage,
            'draws_processed' => $this->drawsProcessed,
            'combinations_inserted' => $this->combinationsInserted,
            'started_at' => $this->startedAt?->format('Y-m-d H:i:s'),
            'finished_at' => $this->finishedAt?->format('Y-m-d H:i:s'),
            'duration_seconds' => $this->durationSeconds,
            'etl_request_payload' => json_encode($this->etlRequestPayload, JSON_THROW_ON_ERROR),
            'etl_response_payload' => $this->etlResponsePayload !== null
                ? json_encode($this->etlResponsePayload, JSON_THROW_ON_ERROR)
                : null,
            'error_message' => $this->errorMessage,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }

    public function markRunning(DateTimeImmutable $startedAt, ?string $stage = null): self
    {
        $clone = clone $this;
        $clone->status = ImportStatus::RUNNING;
        $clone->startedAt = $startedAt;
        $clone->currentStage = $stage;
        $clone->progressPercent = max($this->progressPercent, 1);

        return $clone;
    }

    /**
     * @param array<string, mixed>|null $responsePayload
     */
    public function markSucceeded(
        DateTimeImmutable $finishedAt,
        ?int $drawsProcessed,
        ?int $combinationsInserted,
        ?array $responsePayload,
        ?string $stage = null,
    ): self {
        $clone = clone $this;
        $clone->status = ImportStatus::SUCCEEDED;
        $clone->finishedAt = $finishedAt;
        $clone->durationSeconds = $this->startedAt !== null
            ? (int) ($finishedAt->getTimestamp() - $this->startedAt->getTimestamp())
            : null;
        $clone->drawsProcessed = $drawsProcessed;
        $clone->combinationsInserted = $combinationsInserted;
        $clone->currentStage = $stage;
        $clone->progressPercent = 100;
        $clone->etlResponsePayload = $responsePayload;
        $clone->errorMessage = null;

        return $clone;
    }

    /**
     * @param array<string, mixed>|null $responsePayload
     */
    public function markFailed(
        DateTimeImmutable $finishedAt,
        string $errorMessage,
        ?array $responsePayload = null,
        ?string $stage = null,
    ): self {
        $clone = clone $this;
        $clone->status = ImportStatus::FAILED;
        $clone->finishedAt = $finishedAt;
        $clone->durationSeconds = $this->startedAt !== null
            ? (int) ($finishedAt->getTimestamp() - $this->startedAt->getTimestamp())
            : null;
        $clone->errorMessage = $errorMessage;
        $clone->currentStage = $stage;
        $clone->etlResponsePayload = $responsePayload;
        $clone->progressPercent = max($this->progressPercent, 0);

        return $clone;
    }
}
