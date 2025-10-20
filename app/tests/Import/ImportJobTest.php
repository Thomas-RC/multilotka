<?php

declare(strict_types=1);

namespace Multilotka\Tests\Import;

use DateTimeImmutable;
use JsonException;
use Multilotka\Import\ImportJob;
use Multilotka\Import\ImportMode;
use Multilotka\Import\ImportStatus;
use PHPUnit\Framework\TestCase;

final class ImportJobTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testQueueBuildsPayload(): void
    {
        $now = new DateTimeImmutable('2024-01-01 12:00:00');
        $job = ImportJob::queue(
            10,
            ImportMode::FULL,
            ['path' => 'storage/uploads/sample.txt'],
            $now,
        );

        $payload = $job->toDatabasePayload();

        self::assertSame('queued', $payload['status']);
        self::assertSame(0, $payload['progress_percent']);
        self::assertSame('full', $payload['mode']);
        self::assertSame('storage/uploads/sample.txt', json_decode((string) $payload['etl_request_payload'], true, 512, JSON_THROW_ON_ERROR)['path']);
        self::assertSame('2024-01-01 12:00:00', $payload['created_at']);
    }

    /**
     * @throws JsonException
     */
    public function testTransitionsBetweenStatuses(): void
    {
        $now = new DateTimeImmutable('2024-01-01 12:00:00');
        $job = ImportJob::queue(
            5,
            ImportMode::INCREMENTAL,
            ['path' => 'file'],
            $now,
        );

        $started = new DateTimeImmutable('2024-01-01 12:05:00');
        $running = $job->markRunning($started, 'initializing');

        self::assertSame(ImportStatus::RUNNING, $running->status());
        self::assertSame('initializing', $running->currentStage());
        self::assertSame(1, $running->progressPercent());

        $finished = new DateTimeImmutable('2024-01-01 12:15:00');
        $succeeded = $running->markSucceeded(
            $finished,
            200,
            1200,
            ['summary' => 'ok'],
            'completed',
        );

        self::assertSame(ImportStatus::SUCCEEDED, $succeeded->status());
        self::assertSame(100, $succeeded->progressPercent());
        self::assertSame('completed', $succeeded->currentStage());
        self::assertSame('ok', $succeeded->etlResponsePayload()['summary']);

        $failed = $running->markFailed(
            $finished,
            'Błąd połączenia',
            ['error' => 'connection'],
            'failing',
        );

        self::assertSame(ImportStatus::FAILED, $failed->status());
        self::assertSame('Błąd połączenia', $failed->errorMessage());
        self::assertSame('failing', $failed->currentStage());
    }

    /**
     * @throws JsonException
     */
    public function testFromDatabaseRowRestoresEntity(): void
    {
        $row = [
            'id' => 7,
            'file_id' => 3,
            'mode' => 'full',
            'status' => 'running',
            'progress_percent' => 35,
            'current_stage' => 'parsing',
            'draws_processed' => 120,
            'combinations_inserted' => 0,
            'started_at' => '2024-01-01 10:00:00',
            'finished_at' => null,
            'duration_seconds' => null,
            'etl_request_payload' => json_encode(['path' => 'storage/uploads/sample.txt'], JSON_THROW_ON_ERROR),
            'etl_response_payload' => null,
            'error_message' => null,
            'created_at' => '2024-01-01 09:59:00',
        ];

        $job = ImportJob::fromDatabaseRow($row);

        self::assertSame(7, $job->id());
        self::assertSame(3, $job->fileId());
        self::assertSame(ImportStatus::RUNNING, $job->status());
        self::assertSame(35, $job->progressPercent());
        self::assertSame('parsing', $job->currentStage());
    }
}
