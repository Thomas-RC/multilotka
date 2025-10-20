<?php

declare(strict_types=1);

namespace Multilotka\Import;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;

final class UploadedFileRepository implements UploadedFileRepositoryInterface
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * @throws DBALException
     */
    public function latestValidated(): ?UploadedFileRecord
    {
        $row = $this->connection->fetchAssociative(
            <<<'SQL'
            SELECT *
            FROM uploaded_files
            WHERE status = :status
            ORDER BY uploaded_at DESC, id DESC
            LIMIT 1
            SQL,
            ['status' => 'validated'],
        );

        return $row === false ? null : UploadedFileRecord::fromDatabaseRow($row);
    }

    /**
     * @throws DBALException
     */
    public function findById(int $id): ?UploadedFileRecord
    {
        $row = $this->connection->fetchAssociative(
            'SELECT * FROM uploaded_files WHERE id = :id LIMIT 1',
            ['id' => $id],
        );

        return $row === false ? null : UploadedFileRecord::fromDatabaseRow($row);
    }

    /**
     * @throws DBALException
     */
    public function save(UploadedFileRecord $record): UploadedFileRecord
    {
        $payload = $record->toDatabasePayload();
        $this->connection->insert('uploaded_files', $payload);
        $id = (int) $this->connection->lastInsertId();

        return $record->withId($id);
    }

    /**
     * @throws DBALException
     */
    public function markAsArchived(int $id): void
    {
        $this->connection->update(
            'uploaded_files',
            ['status' => 'archived'],
            ['id' => $id],
        );
    }
}
