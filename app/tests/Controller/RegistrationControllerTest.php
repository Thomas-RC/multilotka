<?php

declare(strict_types=1);

namespace Multilotka\Tests\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Multilotka\Controller\RegistrationController;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

/**
 * @runTestsInSeparateProcesses
 */
final class RegistrationControllerTest extends TestCase
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

        putenv('APP_URL=http://tests.local');
        putenv('MAILER_FROM=no-reply@tests.local');
        putenv('MAILER_DSN=null://null');

        session_save_path(sys_get_temp_dir());
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_GET = [];
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }
    }

    public function testHandleWithDuplicateEmailShowsError(): void
    {
        $this->connection->insert('users', [
            'first_name' => 'Existing',
            'last_name' => 'User',
            'email' => 'existing@example.com',
            'password_hash' => password_hash('SecretPass123', PASSWORD_DEFAULT),
            'confirmation_token' => 'abc',
            'confirmed_at' => null,
            'created_at' => '2024-09-05 12:00:00',
            'updated_at' => '2024-09-05 12:00:00',
            'last_login_at' => null,
        ]);

        $_POST = [
            'firstName' => 'Jan',
            'lastName' => 'Kowalski',
            'email' => 'existing@example.com',
            'password' => 'SecretPass123',
            'passwordConfirmation' => 'SecretPass123',
            'terms' => '1',
        ];

        $session = new SessionManager();
        $controller = new RegistrationController($this->twig, $this->connection, $session);
        $output = $controller->handle();

        self::assertStringContainsString('Istnieje już konto powiązane', $output);
    }

    public function testHandleCreatesUserAndReturnsSuccessScreen(): void
    {
        $_POST = [
            'firstName' => 'Anna',
            'lastName' => 'Nowak',
            'email' => 'anna@example.com',
            'password' => 'VeryStrongPass1',
            'passwordConfirmation' => 'VeryStrongPass1',
            'terms' => '1',
        ];

        $session = new SessionManager();
        $controller = new RegistrationController($this->twig, $this->connection, $session);
        $output = $controller->handle();

        self::assertStringContainsString('Potwierdź rejestrację', $output);
        $user = $this->connection->fetchAssociative('SELECT * FROM users WHERE email = :email', [
            'email' => 'anna@example.com',
        ]);
        self::assertNotFalse($user);
        self::assertNotEmpty($user['confirmation_token']);
    }

    public function testConfirmActivatesUserAndRedirects(): void
    {
        $this->connection->insert('users', [
            'first_name' => 'Anna',
            'last_name' => 'Nowak',
            'email' => 'anna@example.com',
            'password_hash' => password_hash('VeryStrongPass1', PASSWORD_DEFAULT),
            'confirmation_token' => 'token-xyz',
            'confirmed_at' => null,
            'created_at' => '2024-09-05 12:00:00',
            'updated_at' => '2024-09-05 12:00:00',
            'last_login_at' => null,
        ]);

        $_GET['token'] = 'token-xyz';

        $session = new SessionManager();
        $controller = new RegistrationController($this->twig, $this->connection, $session);
        $response = $controller->confirm();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/login', $response->location());

        $user = $this->connection->fetchAssociative('SELECT * FROM users WHERE email = :email', [
            'email' => 'anna@example.com',
        ]);

        self::assertNotNull($user['confirmed_at']);
        self::assertNull($user['confirmation_token']);
        self::assertSame('Konto zostało potwierdzone. Możesz się zalogować.', $session->getFlash('success'));
    }
}
