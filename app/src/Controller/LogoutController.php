<?php

declare(strict_types=1);

namespace Multilotka\Controller;

use Doctrine\DBAL\Connection;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use Twig\Environment;

final class LogoutController
{
    public function __construct(
        private readonly Environment $twig,
        private readonly Connection $connection,
        private readonly SessionManager $session,
    ) {
    }

    public function signOut(): RedirectResponse
    {
        $this->session->remove('user_id');
        $this->session->remove('user_email');
        $this->session->remove('user_name');
        $this->session->regenerate();

        return new RedirectResponse('/login');
    }
}
