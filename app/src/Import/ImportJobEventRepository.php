<?php

declare(strict_types=1);

namespace Multilotka\Import;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;

final class ImportJobEventRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws DBALException
     * @throws JsonException
     */
    public function record(ImportJobEvent $event): ImportJobEvent
    {
        $payload = $event->toDatabasePayload();
        $this->connection->insert('import_job_events', $payload);
        $id = (int) $this->connection->lastInsertId();

        return $event->withId($id);
    }

    /**
     * @return ImportJobEvent[]
     *
     * @throws DBALException
     * @throws JsonException
     */
    public function findRecentForJob(int $jobId, int $limit = 25): array
    {
        $rows = $this->connection->fetchAllAssociative(
            <<<'SQL'
            SELECT *
            FROM import_job_events
            WHERE import_job_id = :jobId
            ORDER BY created_at DESC, id DESC
            LIMIT :limit
            SQL,
            ['jobId' => $jobId, 'limit' => $limit],
            ['jobId' => \PDO::PARAM_INT, 'limit' => \PDO::PARAM_INT],
        );

        return array_map(static fn (array $row): ImportJobEvent => ImportJobEvent::fromDatabaseRow($row), $rows);
    }
}
