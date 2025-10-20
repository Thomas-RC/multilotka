<?php

declare(strict_types=1);

namespace Multilotka\Tests\Import;

use DateTimeImmutable;
use JsonException;
use Multilotka\Import\ImportJobEvent;
use Multilotka\Import\ImportJobEventType;
use PHPUnit\Framework\TestCase;

final class ImportJobEventTest extends TestCase
{
    /**
     * @throws JsonException
     */
    public function testToDatabasePayloadEncodesPayload(): void
    {
        $event = ImportJobEvent::create(
            15,
            ImportJobEventType::QUEUED,
            ['message' => 'started'],
            new DateTimeImmutable('2024-01-01 12:00:00'),
        );

        $payload = $event->toDatabasePayload();

        self::assertSame(15, $payload['import_job_id']);
        self::assertSame('queued', $payload['event_type']);
        self::assertSame('started', json_decode((string) $payload['payload'], true, 512, JSON_THROW_ON_ERROR)['message']);
    }

    /**
     * @throws JsonException
     */
    public function testFromDatabaseRow(): void
    {
        $row = [
            'id' => 4,
            'import_job_id' => 3,
            'event_type' => 'progress_update',
            'payload' => json_encode(['percent' => 50], JSON_THROW_ON_ERROR),
            'created_at' => '2024-01-01 12:05:00',
        ];

        $event = ImportJobEvent::fromDatabaseRow($row);

        self::assertSame(4, $event->id());
        self::assertSame(ImportJobEventType::PROGRESS_UPDATE, $event->type());
        self::assertSame(50, $event->payload()['percent']);
    }
}
