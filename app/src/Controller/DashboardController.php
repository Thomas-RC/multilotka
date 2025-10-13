<?php

declare(strict_types=1);

namespace Multilotka\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use Multilotka\Support\UserRepository;
use Twig\Environment;

final class DashboardController
{
    private UserRepository $users;

    public function __construct(
        private readonly Environment $twig,
        private readonly Connection $connection,
        private readonly SessionManager $session,
    ) {
        $this->users = new UserRepository($connection);
    }

    public function index(): string|RedirectResponse
    {
        if (!$this->session->has('user_id')) {
            return new RedirectResponse('/login');
        }

        $userId = (int) $this->session->get('user_id');

        try {
            $user = $this->users->findById($userId);
        } catch (DBALException) {
            return $this->twig->render('dashboard/index.html.twig', [
                'pageTitle' => 'Panel główny',
                'user' => null,
                'errorMessage' => 'Nie udało się pobrać danych użytkownika. Spróbuj ponownie później.',
            ]);
        }

        if ($user === null) {
            $this->session->remove('user_id');
            $this->session->remove('user_email');
            $this->session->remove('user_name');

            return new RedirectResponse('/login');
        }

        return $this->twig->render('dashboard/index.html.twig', [
            'pageTitle' => 'Panel główny',
            'user' => $user,
            'errorMessage' => null,
        ]);
    }
}
