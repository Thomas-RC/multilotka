<?php

declare(strict_types=1);

namespace Multilotka\Tests\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Multilotka\Controller\LoginController;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @runTestsInSeparateProcesses
 */
final class LoginControllerTest extends TestCase
{
    private Connection $connection;
    private Environment $twig;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Test wymaga rozszerzenia pdo_sqlite.');
        }

        $this->twig = new Environment(
            new FilesystemLoader(__DIR__ . '/../../templates')
        );

        $this->connection = DriverManager::getConnection([
            'driver' => 'pdo_sqlite',
            'memory' => true,
        ]);

        $this->connection->executeStatement(
            <<<SQL
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                first_name VARCHAR(80) NOT NULL,
                last_name VARCHAR(80) NOT NULL,
                email VARCHAR(180) NOT NULL UNIQUE,
                password_hash VARCHAR(255) NOT NULL,
                confirmation_token VARCHAR(64) DEFAULT NULL,
                confirmed_at DATETIME DEFAULT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                last_login_at DATETIME DEFAULT NULL
            )
            SQL
        );

        session_save_path(sys_get_temp_dir());
        $_POST = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function testAuthenticateRejectsInvalidCredentials(): void
    {
        $_POST = [
            'email' => 'missing@example.com',
            'password' => 'secret',
        ];

        $session = new SessionManager();
        $controller = new LoginController($this->twig, $this->connection, $session);
        $output = $controller->authenticate();

        self::assertIsString($output);
        self::assertStringContainsString('Niepoprawne dane logowania', $output);
    }

    public function testAuthenticateRedirectsOnValidCredentials(): void
    {
        $this->connection->insert('users', [
            'first_name' => 'Jan',
            'last_name' => 'Kowalski',
            'email' => 'jan@example.com',
            'password_hash' => password_hash('SecretPass123', PASSWORD_DEFAULT),
            'confirmation_token' => null,
            'confirmed_at' => '2024-09-05 12:00:00',
            'created_at' => '2024-09-01 08:00:00',
            'updated_at' => '2024-09-01 08:00:00',
            'last_login_at' => null,
        ]);

        $_POST = [
            'email' => 'jan@example.com',
            'password' => 'SecretPass123',
        ];

        $session = new SessionManager();
        $controller = new LoginController($this->twig, $this->connection, $session);
        $response = $controller->authenticate();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/dashboard', $response->location());

        self::assertSame('jan@example.com', $_SESSION['user_email'] ?? null);
        self::assertSame('Jan', $_SESSION['user_name'] ?? null);
    }
}
