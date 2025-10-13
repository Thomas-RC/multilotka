<?php

declare(strict_types=1);

namespace Multilotka\Core;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception as DBALException;

final class DatabaseConnection
{
    /**
     * @throws DBALException
     */
    public static function create(): Connection
    {
        Environment::bootstrap();

        $dbname = Environment::get('DB_NAME', Environment::get('MARIADB_DATABASE', 'multilotka'));
        $port = (int) Environment::get('DB_PORT', Environment::get('MARIADB_PORT', 3306));
        $user = Environment::get('DB_USER', Environment::get('MARIADB_USER', 'root'));
        $password = Environment::get('DB_PASSWORD', Environment::get('MARIADB_PASSWORD', ''));
        $configuredHost = Environment::get('DB_HOST', Environment::get('MARIADB_HOST'));

        $hostCandidates = [];
        if (is_string($configuredHost) && $configuredHost !== '') {
            $hostCandidates[] = $configuredHost;
        }
        $hostCandidates[] = '127.0.0.1';
        $hostCandidates[] = 'db';
        $hostCandidates = array_values(array_unique($hostCandidates));

        $lastException = null;

        foreach ($hostCandidates as $host) {
            $connection = DriverManager::getConnection([
                'dbname' => $dbname,
                'user' => $user,
                'password' => $password,
                'host' => $host,
                'port' => $port,
                'driver' => 'pdo_mysql',
                'charset' => 'utf8mb4',
            ]);

            try {
                $connection->connect();

                return $connection;
            } catch (DBALException $exception) {
                $lastException = $exception;
            }
        }

        if ($lastException !== null) {
            throw $lastException;
        }

        return DriverManager::getConnection([
            'dbname' => $dbname,
            'user' => $user,
            'password' => $password,
            'host' => '127.0.0.1',
            'port' => $port,
            'driver' => 'pdo_mysql',
            'charset' => 'utf8mb4',
        ]);
    }
}
