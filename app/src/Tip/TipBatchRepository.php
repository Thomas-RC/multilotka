<?php

declare(strict_types=1);

namespace Multilotka\Tip;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;

final class TipBatchRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function save(TipBatch $batch): TipBatch
    {
        $data = [
            'import_job_id' => $batch->importJobId(),
            'user_id' => $batch->userId(),
            'status' => $batch->status(),
            'confidence_floor' => $batch->confidenceFloor(),
            'average_confidence' => $batch->averageConfidence(),
            'generated_at' => $batch->generatedAt()->format('Y-m-d H:i:s'),
            'notes' => $batch->notes(),
        ];

        if ($batch->id() === null) {
            $this->connection->insert('tip_batches', $data);
            $id = (int) $this->connection->lastInsertId();

            return $batch->withId($id);
        }

        $this->connection->update('tip_batches', $data, ['id' => $batch->id()]);

        return $batch;
    }

    public function findById(int $id): ?TipBatch
    {
        $result = $this->connection->fetchAssociative(
            'SELECT * FROM tip_batches WHERE id = ?',
            [$id]
        );

        if ($result === false) {
            return null;
        }

        return $this->hydrate($result);
    }

    /**
     * Pobiera ostatni batch wygenerowany przez użytkownika
     */
    public function findLatestByUser(int $userId): ?TipBatch
    {
        $result = $this->connection->fetchAssociative(
            'SELECT * FROM tip_batches WHERE user_id = ? ORDER BY generated_at DESC LIMIT 1',
            [$userId]
        );

        if ($result === false) {
            return null;
        }

        return $this->hydrate($result);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): TipBatch
    {
        return TipBatch::fromDatabase(
            id: (int) $row['id'],
            importJobId: $row['import_job_id'] !== null ? (int) $row['import_job_id'] : null,
            userId: (int) $row['user_id'],
            status: (string) $row['status'],
            confidenceFloor: (float) $row['confidence_floor'],
            averageConfidence: $row['average_confidence'] !== null ? (float) $row['average_confidence'] : null,
            generatedAt: new DateTimeImmutable($row['generated_at']),
            notes: $row['notes'] !== null ? (string) $row['notes'] : null,
        );
    }
}
