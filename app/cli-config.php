<?php

declare(strict_types=1);

use Multilotka\Core\DatabaseConnection;
use Multilotka\Core\Environment;
use Multilotka\Support\MigrationFactory;

require __DIR__ . '/../vendor/autoload.php';

Environment::bootstrap();
$connection = DatabaseConnection::create();

return MigrationFactory::createFromConnection($connection);
