<?php

declare(strict_types=1);

use Multilotka\Core\Application;
use Multilotka\Core\DatabaseConnection;
use Multilotka\Core\Environment;
use Multilotka\Core\SessionManager;
use Multilotka\Core\TwigFactory;

require __DIR__ . '/../vendor/autoload.php';

Environment::bootstrap();

$session = new SessionManager();

try {
    $connection = DatabaseConnection::create();
} catch (\Doctrine\DBAL\Exception $exception) {
    http_response_code(500);
    echo 'Database connection failed.';

    return;
}

$twig = TwigFactory::create();
$application = new Application($twig, $connection, $session);
$application->handle($_SERVER['REQUEST_URI'] ?? '/', $_SERVER['REQUEST_METHOD'] ?? 'GET');
