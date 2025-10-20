<?php

declare(strict_types=1);

namespace Multilotka\Import;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;

final class ImportJobRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws DBALException
     * @throws JsonException
     */
    public function create(ImportJob $job): ImportJob
    {
        $payload = $job->toDatabasePayload();
        $this->connection->insert('import_jobs', $payload);
        $id = (int) $this->connection->lastInsertId();

        return $job->withId($id);
    }

    /**
     * @throws DBALException
     * @throws JsonException
     */
    public function update(ImportJob $job): void
    {
        $id = $job->id();
        if ($id === null) {
            throw new \LogicException('Cannot update an import job without identifier.');
        }

        $payload = $job->toDatabasePayload();
        unset($payload['created_at'], $payload['file_id'], $payload['mode']);

        $this->connection->update('import_jobs', $payload, ['id' => $id]);
    }

    /**
     * @throws DBALException
     * @throws JsonException
     */
    public function findById(int $id): ?ImportJob
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM import_jobs WHERE id = :id LIMIT 1',
            ['id' => $id],
        );

        return $row === false ? null : ImportJob::fromDatabaseRow($row);
    }

    /**
     * @throws DBALException
     * @throws JsonException
     */
    public function findLatestForFile(int $fileId): ?ImportJob
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
            SELECT *
            FROM import_jobs
            WHERE file_id = :fileId
            ORDER BY created_at DESC, id DESC
            LIMIT 1
            SQL,
            ['fileId' => $fileId],
        );

        return $row === false ? null : ImportJob::fromDatabaseRow($row);
    }
}
