<?php

declare(strict_types=1);

namespace Multilotka\Controller;

use Doctrine\DBAL\Connection;
use Multilotka\Core\SessionManager;
use Twig\Environment;

final class HomeController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Connection $connection,
        private readonly SessionManager $session,
    ) {
    }

    public function index(): string
    {
        return $this->twig->render('home/index.html.twig', [
            'pageTitle' => 'Multilotka',
            'isAuthenticated' => $this->session->has('user_id'),
        ]);
    }
}
