<?php

declare(strict_types=1);

namespace Multilotka\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use Multilotka\Import\UploadedFileRepository;
use Multilotka\Import\UploadedFileRecord;
use Multilotka\Support\UserRepository;
use Twig\Environment;

final class DashboardController
{
    private UserRepository $users;
    private UploadedFileRepository $uploads;

    public function __construct(
        private readonly Environment $twig,
        private readonly Connection $connection,
        private readonly SessionManager $session,
    ) {
        $this->users = new UserRepository($connection);
        $this->uploads = new UploadedFileRepository($connection);
    }

    public function index(): string|RedirectResponse
    {
        if (!$this->session->has('user_id')) {
            return new RedirectResponse('/login');
        }

        $userId = (int) $this->session->get('user_id');
        $flashes = $this->session->allFlashes();

        try {
            $user = $this->users->findById($userId);
        } catch (DBALException) {
            return $this->twig->render('dashboard/index.html.twig', [
                'pageTitle' => 'Panel główny',
                'user' => null,
                'errorMessage' => 'Nie udało się pobrać danych użytkownika. Spróbuj ponownie później.',
                'flashes' => $flashes,
                'lastUpload' => null,
            ]);
        }

        if ($user === null) {
            $this->session->remove('user_id');
            $this->session->remove('user_email');
            $this->session->remove('user_name');

            return new RedirectResponse('/login');
        }

        $lastUpload = null;
        $uploadError = null;

        try {
            $record = $this->uploads->latestValidated();
            $lastUpload = $record !== null ? $this->toTemplatePayload($record) : null;
        } catch (DBALException) {
            $uploadError = 'Nie udało się odczytać informacji o ostatnim pliku.';
        }

        if ($uploadError !== null) {
            $flashes['error_upload'] = $uploadError;
        }

        return $this->twig->render('dashboard/index.html.twig', [
            'pageTitle' => 'Panel główny',
            'user' => $user,
            'errorMessage' => null,
            'flashes' => $flashes,
            'lastUpload' => $lastUpload,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toTemplatePayload(UploadedFileRecord $record): array
    {
        return [
            'originalName' => $record->originalName(),
            'storedPath' => $record->storedPath(),
            'rowsTotal' => $record->rowsTotal(),
            'drawDateStart' => $record->drawDateStart(),
            'drawDateEnd' => $record->drawDateEnd(),
            'uploadedAt' => $record->uploadedAt(),
        ];
    }
}
