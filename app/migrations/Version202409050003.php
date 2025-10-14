<?php

declare(strict_types=1);

namespace Multilotka\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version202409050003 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Uzupełnia tabelę users o kolumny imienia, nazwiska oraz token potwierdzający zgodnie z nowym schematem.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<SQL
            ALTER TABLE users
                ADD COLUMN IF NOT EXISTS first_name VARCHAR(80) NOT NULL AFTER id,
                ADD COLUMN IF NOT EXISTS last_name VARCHAR(80) NOT NULL AFTER first_name,
                ADD COLUMN IF NOT EXISTS confirmation_token CHAR(64) DEFAULT NULL AFTER password_hash
            SQL
        );

        $this->addSql(
            <<<SQL
            ALTER TABLE users
                ADD UNIQUE INDEX IF NOT EXISTS UNIQ_users_confirmation_token (confirmation_token)
            SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP INDEX UNIQ_users_confirmation_token');
        $this->addSql('ALTER TABLE users DROP COLUMN confirmation_token');
        $this->addSql('ALTER TABLE users DROP COLUMN last_name');
        $this->addSql('ALTER TABLE users DROP COLUMN first_name');
    }
}
