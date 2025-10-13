<?php

declare(strict_types=1);

namespace Multilotka\Controller;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Multilotka\Core\Environment as AppEnvironment;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use Multilotka\Support\Mail\MailerFactory;
use Multilotka\Support\Mail\RegistrationMailer;
use Multilotka\Support\User;
use Multilotka\Support\UserRepository;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Twig\Environment;

final class RegistrationController
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
        return $this->renderForm();
    }

    public function handle(): string
    {
        $input = [
            'firstName' => trim((string) ($_POST['firstName'] ?? '')),
            'lastName' => trim((string) ($_POST['lastName'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'password' => (string) ($_POST['password'] ?? ''),
            'passwordConfirmation' => (string) ($_POST['passwordConfirmation'] ?? ''),
            'terms' => isset($_POST['terms']) ? (string) $_POST['terms'] : '',
        ];

        $errors = $this->validate($input);

        if ($errors !== []) {
            return $this->renderForm($input, $errors);
        }

        try {
            if ($this->users->findByEmail($input['email']) !== null) {
                return $this->renderForm(
                    $input,
                    ['Istnieje już konto powiązane z podanym adresem e-mail.'],
                );
            }
        } catch (DBALException) {
            return $this->renderForm(
                $input,
                ['Wystąpił problem z bazą danych. Spróbuj ponownie później.'],
            );
        }

        $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
        $token = bin2hex(random_bytes(32));
        $now = new DateTimeImmutable('now');

        $user = User::register(
            $input['firstName'],
            $input['lastName'],
            $input['email'],
            $passwordHash,
            $token,
            $now,
        );

        try {
            $this->users->create($user);
        } catch (DBALException) {
            return $this->renderForm(
                $input,
                ['Nie udało się zapisać użytkownika. Spróbuj ponownie później.'],
            );
        }

        $mailDeliveryFailed = false;
        try {
            $mailer = new RegistrationMailer(
                MailerFactory::create(),
                (string) AppEnvironment::get('MAILER_FROM', 'no-reply@multilotka.test'),
                (string) AppEnvironment::get('APP_URL', 'http://localhost:8081')
            );

            $mailer->sendConfirmation(
                $input['email'],
                $token,
                trim($input['firstName'] . ' ' . $input['lastName'])
            );
        } catch (TransportExceptionInterface|\Throwable) {
            $mailDeliveryFailed = true;
        }

        $verificationLink = '/register/confirm?token=' . urlencode($token);

        return $this->twig->render('auth/register_success.html.twig', [
            'pageTitle' => 'Potwierdź rejestrację',
            'email' => $input['email'],
            'verificationLink' => $verificationLink,
            'mailDeliveryFailed' => $mailDeliveryFailed,
        ]);
    }

    public function confirm(): string|RedirectResponse
    {
        $token = (string) ($_GET['token'] ?? '');

        if ($token === '') {
            return $this->renderConfirmationError('Brak tokenu potwierdzającego.');
        }

        try {
            $user = $this->users->findByConfirmationToken($token);
        } catch (DBALException) {
            return $this->renderConfirmationError('Wystąpił problem z bazą danych. Spróbuj ponownie później.');
        }

        if ($user === null) {
            return $this->renderConfirmationError('Nieprawidłowy lub wygasły token potwierdzający.');
        }

        if ($user->isConfirmed()) {
            $this->session->flash('success', 'Twoje konto jest już aktywne. Zaloguj się, aby kontynuować.');

            return new RedirectResponse('/login');
        }

        try {
            $this->users->confirm($user, new DateTimeImmutable('now'));
        } catch (DBALException) {
            return $this->renderConfirmationError('Nie udało się potwierdzić konta. Spróbuj ponownie.');
        }

        $this->session->flash('success', 'Konto zostało potwierdzone. Możesz się zalogować.');

        return new RedirectResponse('/login');
    }

    /**
     * @param array<string, string> $input
     * @param array<int, string> $errors
     */
    private function renderForm(array $input = [], array $errors = []): string
    {
        $defaults = [
            'firstName' => '',
            'lastName' => '',
            'email' => '',
        ];

        return $this->twig->render('auth/register.html.twig', [
            'pageTitle' => 'Rejestracja',
            'old' => array_merge($defaults, array_intersect_key($input, $defaults)),
            'errors' => $errors,
        ]);
    }

    /**
     * @param array<string, string> $input
     *
     * @return array<int, string>
     */
    private function validate(array $input): array
    {
        $errors = [];

        if ($input['firstName'] === '') {
            $errors[] = 'Imię jest wymagane.';
        }

        if ($input['lastName'] === '') {
            $errors[] = 'Nazwisko jest wymagane.';
        }

        if ($input['email'] === '' || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Podaj poprawny adres e-mail.';
        }

        if (mb_strlen($input['password']) < 12) {
            $errors[] = 'Hasło musi mieć co najmniej 12 znaków.';
        }

        if (!preg_match('/[A-Z]/', $input['password']) || !preg_match('/[0-9]/', $input['password'])) {
            $errors[] = 'Hasło musi zawierać co najmniej jedną wielką literę i cyfrę.';
        }

        if ($input['password'] !== $input['passwordConfirmation']) {
            $errors[] = 'Hasła muszą być identyczne.';
        }

        if ($input['terms'] === '') {
            $errors[] = 'Musisz zaakceptować regulamin.';
        }

        return $errors;
    }

    private function renderConfirmationError(string $message): string
    {
        return $this->twig->render('auth/confirmation.html.twig', [
            'pageTitle' => 'Potwierdzenie rejestracji',
            'status' => 'error',
            'message' => $message,
        ]);
    }
}
