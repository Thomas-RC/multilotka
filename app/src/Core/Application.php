<?php

declare(strict_types=1);

namespace Multilotka\Core;

use Doctrine\DBAL\Connection;
use Multilotka\Controller\DashboardController;
use Multilotka\Controller\HomeController;
use Multilotka\Controller\LoginController;
use Multilotka\Controller\LogoutController;
use Multilotka\Controller\RegistrationController;
use Twig\Environment;

final class Application
{
    /**
     * @var array<string, array<string, array{0: class-string, 1: string}>>
     */
    private array $routes;

    public function __construct(
        private readonly Environment $twig,
        private readonly Connection $connection,
        private readonly SessionManager $session,
    ) {
        $this->routes = [
            '/' => [
                'GET' => [HomeController::class, 'index'],
            ],
            '/home' => [
                'GET' => [HomeController::class, 'index'],
            ],
            '/login' => [
                'GET' => [LoginController::class, 'showForm'],
                'POST' => [LoginController::class, 'authenticate'],
            ],
            '/logout' => [
                'GET' => [LogoutController::class, 'signOut'],
            ],
            '/register' => [
                'GET' => [RegistrationController::class, 'showForm'],
                'POST' => [RegistrationController::class, 'handle'],
            ],
            '/register/confirm' => [
                'GET' => [RegistrationController::class, 'confirm'],
            ],
            '/dashboard' => [
                'GET' => [DashboardController::class, 'index'],
            ],
        ];
    }

    public function handle(string $requestUri, string $requestMethod): void
    {
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        $normalizedPath = $this->normalizePath($path);
        $method = strtoupper($requestMethod);

        if (!isset($this->routes[$normalizedPath])) {
            http_response_code(404);
            echo $this->twig->render('errors/404.html.twig');

            return;
        }

        $routeDefinition = $this->routes[$normalizedPath];

        if (!isset($routeDefinition[$method])) {
            http_response_code(405);
            header('Allow: ' . implode(', ', array_keys($routeDefinition)));
            echo $this->twig->render('errors/404.html.twig');

            return;
        }

        [$controllerClass, $action] = $routeDefinition[$method];
        $controller = new $controllerClass($this->twig, $this->connection, $this->session);

        if (!method_exists($controller, $action)) {
            http_response_code(500);
            echo $this->twig->render('errors/500.html.twig');

            return;
        }

        $response = $controller->{$action}();

        if ($response instanceof RedirectResponse) {
            $response->send();

            return;
        }

        if (is_string($response)) {
            echo $response;
        }
    }

    private function normalizePath(string $path): string
    {
        $trimmed = '/' . trim($path, '/');

        return $trimmed === '//' ? '/' : $trimmed;
    }
}
