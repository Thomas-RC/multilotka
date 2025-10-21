<?php

declare(strict_types=1);

namespace Multilotka\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202510200001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tworzy tabele tip_batches i tip_combinations dla systemu generowania rekomendacji.';
    }

    public function up(Schema $schema): void
    {
        // Tabela tip_batches
        $this->addSql(
            <<<SQL
            CREATE TABLE IF NOT EXISTS tip_batches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                import_job_id BIGINT UNSIGNED NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
                confidence_floor DECIMAL(5,2) NOT NULL,
                average_confidence DECIMAL(5,2) NULL,
                generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                notes VARCHAR(255) NULL,
                FOREIGN KEY (import_job_id) REFERENCES import_jobs(id) ON DELETE SET NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_tip_batches_user_id (user_id),
                INDEX idx_tip_batches_generated_at (generated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );

        // Tabela tip_combinations
        $this->addSql(
            <<<SQL
            CREATE TABLE IF NOT EXISTS tip_combinations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tip_batch_id BIGINT UNSIGNED NOT NULL,
                sequence TINYINT UNSIGNED NOT NULL,
                combo CHAR(14) NOT NULL,
                confidence DECIMAL(5,2) NOT NULL,
                due_score DECIMAL(5,2) NULL,
                frequency_score DECIMAL(5,2) NULL,
                gap_score DECIMAL(5,2) NULL,
                FOREIGN KEY (tip_batch_id) REFERENCES tip_batches(id) ON DELETE CASCADE,
                UNIQUE KEY unique_tip_batch_combo (tip_batch_id, combo),
                INDEX idx_tip_combinations_confidence (confidence DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS tip_combinations');
        $this->addSql('DROP TABLE IF EXISTS tip_batches');
    }
}
