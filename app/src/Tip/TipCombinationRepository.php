<?php

declare(strict_types=1);

namespace Multilotka\Tip;

use Doctrine\DBAL\Connection;

final class TipCombinationRepository
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public function save(TipCombination $combination): void
    {
        $data = [
            'tip_batch_id' => $combination->tipBatchId(),
            'sequence' => $combination->sequence(),
            'combo' => $combination->combo(),
            'confidence' => $combination->confidence(),
            'due_score' => $combination->dueScore(),
            'frequency_score' => $combination->frequencyScore(),
            'gap_score' => $combination->gapScore(),
        ];

        if ($combination->id() === null) {
            $this->connection->insert('tip_combinations', $data);
        } else {
            $this->connection->update('tip_combinations', $data, ['id' => $combination->id()]);
        }
    }

    /**
     * Zapisuje wiele kombinacji w jednej transakcji
     *
     * @param array<TipCombination> $combinations
     */
    public function saveMany(array $combinations): void
    {
        $this->connection->beginTransaction();

        try {
            foreach ($combinations as $combination) {
                $this->save($combination);
            }

            $this->connection->commit();
        } catch (\Exception $e) {
            $this->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Pobiera wszystkie kombinacje dla danego batcha
     *
     * @return array<TipCombination>
     */
    public function findByBatchId(int $batchId): array
    {
        $results = $this->connection->fetchAllAssociative(
            'SELECT * FROM tip_combinations WHERE tip_batch_id = ? ORDER BY sequence ASC',
            [$batchId]
        );

        $combinations = [];
        foreach ($results as $row) {
            $combinations[] = $this->hydrate($row);
        }

        return $combinations;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): TipCombination
    {
        return TipCombination::fromDatabase(
            id: (int) $row['id'],
            tipBatchId: (int) $row['tip_batch_id'],
            sequence: (int) $row['sequence'],
            combo: (string) $row['combo'],
            confidence: (float) $row['confidence'],
            dueScore: (float) $row['due_score'],
            frequencyScore: (float) $row['frequency_score'],
            gapScore: (float) $row['gap_score'],
        );
    }
}
