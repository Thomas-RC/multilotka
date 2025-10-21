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

        // Zwiększony timeout dla ciężkich zapytań agregacyjnych
        // GROUP BY na 253M rekordów wymaga więcej czasu
        $client->setTimeout(120);      // 120 sekund na query execution
        $client->setConnectTimeOut(10); // 10 sekund na connection

        return $client;
    }
}
