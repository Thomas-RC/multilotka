<?php

declare(strict_types=1);

namespace Multilotka\Core;

use ClickHouseDB\Client as ClickHouseClient;

final class ClickHouseConnection
{
    /**
     * Tworzy połączenie z ClickHouse na podstawie zmiennych środowiskowych
     */
    public static function create(): ClickHouseClient
    {
        $client = new ClickHouseClient([
            'host' => getenv('CLICKHOUSE_HOST') ?: 'dbh',
            'port' => (int) (getenv('CLICKHOUSE_PORT') ?: '8123'),
            'username' => getenv('CLICKHOUSE_USER') ?: 'multilotka',
            'password' => getenv('CLICKHOUSE_PASSWORD') ?: 'multilotka',
        ]);

        $database = getenv('CLICKHOUSE_DATABASE') ?: 'analytics';
        $client->database($database);

        return $client;
    }
}
