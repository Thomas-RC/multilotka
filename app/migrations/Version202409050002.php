<?php

declare(strict_types=1);

namespace Multilotka\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202409050002 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Dodaje pola potwierdzenia e-mail i czas logowania do tabeli users.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            ALTER TABLE users
            ADD confirmation_token VARCHAR(64) DEFAULT NULL,
            ADD confirmed_at DATETIME DEFAULT NULL,
            ADD last_login_at DATETIME DEFAULT NULL
            SQL
        );

        $this->addSql('CREATE UNIQUE INDEX UNIQ_users_confirmation_token ON users (confirmation_token)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX UNIQ_users_confirmation_token ON users');
        $this->addSql(
            <<<SQL
            ALTER TABLE users
            DROP confirmation_token,
            DROP confirmed_at,
            DROP last_login_at
            SQL
        );
    }
}
