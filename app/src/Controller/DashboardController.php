<?php

declare(strict_types=1);

namespace Multilotka\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;
use Multilotka\Core\ClickHouseConnection;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use Multilotka\Import\ImportFormatter;
use Multilotka\Import\ImportJobEvent;
use Multilotka\Import\ImportJobEventRepository;
use Multilotka\Import\ImportJobRepository;
use Multilotka\Import\ImportMode;
use Multilotka\Import\UploadedFileRepository;
use Multilotka\Import\UploadedFileRecord;
use Multilotka\Support\UserRepository;
use Twig\Environment;

final class DashboardController
{
    private UserRepository $users;
    private UploadedFileRepository $uploads;
    private ImportJobRepository $importJobs;
    private ImportJobEventRepository $importJobEvents;

    public function __construct(
        private readonly Environment $twig,
        private readonly Connection $connection,
        private readonly SessionManager $session,
    ) {
        $this->users = new UserRepository($connection);
        $this->uploads = new UploadedFileRepository($connection);
        $this->importJobs = new ImportJobRepository($connection);
        $this->importJobEvents = new ImportJobEventRepository($connection);
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
        $lastImportJob = null;
        $importEvents = [];
        $uploadError = null;

        try {
            $record = $this->uploads->latestValidated();
            if ($record !== null) {
                $lastUpload = $this->toTemplatePayload($record);

                if ($record->id() !== null) {
                    try {
                        $job = $this->importJobs->findLatestForFile($record->id());
                        if ($job !== null && $job->id() !== null) {
                            $lastImportJob = ImportFormatter::formatJob($job);
                            $events = $this->importJobEvents->findRecentForJob($job->id(), 3);
                            $importEvents = array_map(
                                static fn (ImportJobEvent $event): array => ImportFormatter::formatEvent($event),
                                $events,
                            );
                        }
                    } catch (DBALException|JsonException) {
                        $flashes['error_import'] = 'Nie udało się pobrać statusu importu.';
                    }
                }
            }
        } catch (DBALException) {
            $uploadError = 'Nie udało się odczytać informacji o ostatnim pliku.';
        }

        if ($uploadError !== null) {
            $flashes['error_upload'] = $uploadError;
        }

        $importModes = [
            [
                'value' => ImportMode::FULL->value,
                'label' => 'Pełny import (ponowne przeliczenie całości)',
            ],
            [
                'value' => ImportMode::INCREMENTAL->value,
                'label' => 'Import przyrostowy (tylko nowe losowania)',
            ],
        ];

        $selectedMode = (string) $this->session->get('last_import_mode', ImportMode::FULL->value);

        $importStatusUrl = '/dashboard/import/status' . ($lastUpload !== null ? '?file_id=' . $lastUpload['id'] : '');

        // Sprawdź czy w ClickHouse są dane do generowania tipów
        $hasTipData = $this->checkClickHouseData();

        return $this->twig->render('dashboard/index.html.twig', [
            'pageTitle' => 'Panel główny',
            'user' => $user,
            'errorMessage' => null,
            'flashes' => $flashes,
            'lastUpload' => $lastUpload,
            'lastImportJob' => $lastImportJob,
            'importEvents' => $importEvents,
            'importModes' => $importModes,
            'selectedImportMode' => $selectedMode,
            'importStatusUrl' => $importStatusUrl,
            'hasTipData' => $hasTipData,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toTemplatePayload(UploadedFileRecord $record): array
    {
        return [
            'id' => $record->id(),
            'originalName' => $record->originalName(),
            'storedPath' => $record->storedPath(),
            'rowsTotal' => $record->rowsTotal(),
            'drawDateStart' => $record->drawDateStart(),
            'drawDateEnd' => $record->drawDateEnd(),
            'uploadedAt' => $record->uploadedAt(),
        ];
    }

    /**
     * Sprawdza czy w ClickHouse są dane losowań potrzebne do generowania tipów
     */
    private function checkClickHouseData(): bool
    {
        try {
            $clickhouse = ClickHouseConnection::create();
            $result = $clickhouse->select('SELECT count(DISTINCT draw_number) as total FROM analytics.draws');
            $rows = $result->rows();

            return isset($rows[0]['total']) && (int) $rows[0]['total'] > 0;
        } catch (\Exception) {
            // W przypadku błędu połączenia z ClickHouse, ukrywamy przycisk
            return false;
        }
    }
}
