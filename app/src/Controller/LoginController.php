<?php

declare(strict_types=1);

namespace Multilotka\Controller;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use Multilotka\Support\UserRepository;
use Twig\Environment;

final class LoginController
{
    private UserRepository $users;

    public function __construct(
        private readonly Environment $twig,
        private readonly Connection $connection,
        private readonly SessionManager $session,
    ) {
        $this->users = new UserRepository($connection);
    }

    public function showForm(): string
    {
        $successMessage = $this->session->getFlash('success');

        return $this->renderForm([], ['email' => ''], $successMessage);
    }

    public function authenticate(): string|RedirectResponse
    {
        $email = trim((string) ($_POST['email'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');

        $errors = [];

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Podaj poprawny adres e-mail.';
        }

        if ($password === '') {
            $errors[] = 'Hasło jest wymagane.';
        }

        $old = ['email' => $email];

        if ($errors !== []) {
            return $this->renderForm($errors, $old);
        }

        try {
            $user = $this->users->findByEmail($email);
        } catch (DBALException) {
            return $this->renderForm(
                ['Wystąpił problem z bazą danych. Spróbuj ponownie później.'],
                $old,
            );
        }

        if ($user === null || !$user->passwordMatches($password)) {
            return $this->renderForm(['Niepoprawne dane logowania.'], $old);
        }

        if (!$user->isConfirmed()) {
            return $this->renderForm(
                ['Potwierdź adres e-mail, zanim się zalogujesz.'],
                $old,
            );
        }

        $this->session->regenerate();
        $this->session->set('user_id', $user->id());
        $this->session->set('user_email', $user->email());
        $this->session->set('user_name', $user->firstName());

        try {
            $this->users->recordLogin($user, new DateTimeImmutable('now'));
        } catch (DBALException) {
            // Ignorujemy incydentalny błąd aktualizacji ostatniego logowania.
        }

        return new RedirectResponse('/dashboard');
    }

    /**
     * @param array<int, string> $errors
     * @param array<string, string> $old
     */
    private function renderForm(array $errors, array $old, ?string $successMessage = null): string
    {
        return $this->twig->render('auth/login.html.twig', [
            'pageTitle' => 'Logowanie',
            'errors' => $errors,
            'old' => $old,
            'successMessage' => $successMessage,
        ]);
    }
}
