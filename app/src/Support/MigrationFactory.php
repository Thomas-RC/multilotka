<?php

declare(strict_types=1);

namespace Multilotka\Support;

use Doctrine\DBAL\Connection;
use Doctrine\Migrations\Configuration\Connection\ExistingConnection;
use Doctrine\Migrations\Configuration\Migration\ConfigurationArray;
use Doctrine\Migrations\DependencyFactory;

final class MigrationFactory
{
    public static function createFromConnection(Connection $connection): DependencyFactory
    {
        $configuration = new ConfigurationArray([
            'migrations_paths' => [
                'Multilotka\\Migrations' => dirname(__DIR__, 2) . '/migrations',
            ],
            'all_or_nothing' => true,
            'check_database_platform' => true,
        ]);

        return DependencyFactory::fromConnection(
            $configuration,
            new ExistingConnection($connection),
        );
    }
}
