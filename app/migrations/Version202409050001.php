<?php

declare(strict_types=1);

namespace Multilotka\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202409050001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Tworzy tabelę users dla uwierzytelniania i profili użytkowników.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            CREATE TABLE users (
                id BIGINT UNSIGNED AUTO_INCREMENT NOT NULL,
                first_name VARCHAR(80) NOT NULL,
                last_name VARCHAR(80) NOT NULL,
                email VARCHAR(191) NOT NULL,
                password_hash CHAR(60) NOT NULL,
                confirmation_token CHAR(64) DEFAULT NULL,
                status ENUM('pending','active','suspended') NOT NULL DEFAULT 'pending',
                is_admin TINYINT(1) NOT NULL DEFAULT 0,
                confirmed_at DATETIME DEFAULT NULL,
                last_login_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE INDEX UNIQ_users_email (email),
                UNIQUE INDEX UNIQ_users_confirmation_token (confirmation_token),
                INDEX IDX_users_status (status),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE users');
    }
}
