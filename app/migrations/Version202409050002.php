<?php

declare(strict_types=1);

namespace Multilotka\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202409050002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dodaje tabele wspierające proces importu, generowania tipów oraz logowania zdarzeń.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            CREATE TABLE email_verifications (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                token CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                confirmed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE INDEX UNIQ_email_verifications_token (token),
                INDEX IDX_email_verifications_user (user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_email_verifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE password_reset_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                token CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE INDEX UNIQ_password_reset_tokens_token (token),
                INDEX IDX_password_reset_tokens_user (user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_password_reset_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE user_sessions (
                id CHAR(36) NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                ip_address VARBINARY(16) DEFAULT NULL,
                user_agent VARCHAR(255) DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                INDEX IDX_user_sessions_user_active (user_id, is_active),
                PRIMARY KEY(id),
                CONSTRAINT FK_user_sessions_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE uploaded_files (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                user_id BIGINT UNSIGNED DEFAULT NULL,
                original_name VARCHAR(255) NOT NULL,
                stored_path VARCHAR(255) NOT NULL,
                sha256 CHAR(64) NOT NULL,
                rows_total INT UNSIGNED NOT NULL,
                draw_date_start DATE DEFAULT NULL,
                draw_date_end DATE DEFAULT NULL,
                status ENUM('pending','validated','invalid','archived') NOT NULL DEFAULT 'pending',
                validation_errors JSON DEFAULT NULL,
                uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX IDX_uploaded_files_user (user_id),
                INDEX IDX_uploaded_files_status_uploaded (status, uploaded_at),
                PRIMARY KEY(id),
                CONSTRAINT FK_uploaded_files_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE import_jobs (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                file_id BIGINT UNSIGNED NOT NULL,
                mode ENUM('full','incremental') NOT NULL,
                status ENUM('queued','running','succeeded','failed','cancelled') NOT NULL DEFAULT 'queued',
                progress_percent TINYINT UNSIGNED NOT NULL DEFAULT 0,
                current_stage VARCHAR(50) DEFAULT NULL,
                draws_processed INT UNSIGNED DEFAULT NULL,
                combinations_inserted BIGINT UNSIGNED DEFAULT NULL,
                started_at DATETIME DEFAULT NULL,
                finished_at DATETIME DEFAULT NULL,
                duration_seconds INT UNSIGNED DEFAULT NULL,
                etl_request_payload JSON DEFAULT NULL,
                etl_response_payload JSON DEFAULT NULL,
                error_message TEXT DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX IDX_import_jobs_file (file_id),
                INDEX IDX_import_jobs_status_created (status, created_at),
                INDEX IDX_import_jobs_mode_status (mode, status),
                PRIMARY KEY(id),
                CONSTRAINT FK_import_jobs_file FOREIGN KEY (file_id) REFERENCES uploaded_files (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE import_job_events (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                import_job_id BIGINT UNSIGNED NOT NULL,
                event_type ENUM('queued','connection_established','batch_inserted','progress_update','completed','failed','cancelled') NOT NULL,
                payload JSON DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX IDX_import_job_events_job_created (import_job_id, created_at),
                PRIMARY KEY(id),
                CONSTRAINT FK_import_job_events_job FOREIGN KEY (import_job_id) REFERENCES import_jobs (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE tip_batches (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                import_job_id BIGINT UNSIGNED DEFAULT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
                confidence_floor DECIMAL(5,2) NOT NULL,
                average_confidence DECIMAL(5,2) DEFAULT NULL,
                generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                notes VARCHAR(255) DEFAULT NULL,
                INDEX IDX_tip_batches_user_generated (user_id, generated_at),
                INDEX IDX_tip_batches_import_job (import_job_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_tip_batches_import_job FOREIGN KEY (import_job_id) REFERENCES import_jobs (id) ON DELETE SET NULL,
                CONSTRAINT FK_tip_batches_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE tip_combinations (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                tip_batch_id BIGINT UNSIGNED NOT NULL,
                sequence TINYINT UNSIGNED NOT NULL,
                combo CHAR(14) NOT NULL,
                confidence DECIMAL(5,2) NOT NULL,
                due_score DECIMAL(5,2) DEFAULT NULL,
                frequency_score DECIMAL(5,2) DEFAULT NULL,
                gap_score DECIMAL(5,2) DEFAULT NULL,
                UNIQUE INDEX UNIQ_tip_combinations_batch_combo (tip_batch_id, combo),
                INDEX IDX_tip_combinations_batch_sequence (tip_batch_id, sequence),
                PRIMARY KEY(id),
                CONSTRAINT FK_tip_combinations_batch FOREIGN KEY (tip_batch_id) REFERENCES tip_batches (id) ON DELETE CASCADE
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE export_files (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                tip_batch_id BIGINT UNSIGNED NOT NULL,
                file_path VARCHAR(255) NOT NULL,
                format ENUM('csv','json') NOT NULL DEFAULT 'csv',
                combination_count INT UNSIGNED NOT NULL,
                generated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                generated_by_user_id BIGINT UNSIGNED DEFAULT NULL,
                checksum CHAR(64) DEFAULT NULL,
                INDEX IDX_export_files_batch_generated (tip_batch_id, generated_at),
                INDEX IDX_export_files_generated_by (generated_by_user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_export_files_batch FOREIGN KEY (tip_batch_id) REFERENCES tip_batches (id) ON DELETE CASCADE,
                CONSTRAINT FK_export_files_generated_by FOREIGN KEY (generated_by_user_id) REFERENCES users (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );

        $this->addSql(
            <<<SQL
            CREATE TABLE system_events (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                user_id BIGINT UNSIGNED DEFAULT NULL,
                event_type VARCHAR(50) NOT NULL,
                context JSON DEFAULT NULL,
                ip_address VARBINARY(16) DEFAULT NULL,
                occurred_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX IDX_system_events_type_occurred (event_type, occurred_at),
                INDEX IDX_system_events_user (user_id),
                PRIMARY KEY(id),
                CONSTRAINT FK_system_events_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE system_events');
        $this->addSql('DROP TABLE export_files');
        $this->addSql('DROP TABLE tip_combinations');
        $this->addSql('DROP TABLE tip_batches');
        $this->addSql('DROP TABLE import_job_events');
        $this->addSql('DROP TABLE import_jobs');
        $this->addSql('DROP TABLE uploaded_files');
        $this->addSql('DROP TABLE user_sessions');
        $this->addSql('DROP TABLE password_reset_tokens');
        $this->addSql('DROP TABLE email_verifications');
    }
}
