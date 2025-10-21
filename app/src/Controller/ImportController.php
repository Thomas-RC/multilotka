<?php

declare(strict_types=1);

namespace Multilotka\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use Multilotka\Import\ImportFormatter;
use Multilotka\Import\ImportCoordinator;
use Multilotka\Import\ImportJobEvent;
use Multilotka\Import\ImportJobEventRepository;
use Multilotka\Import\ImportJobRepository;
use Multilotka\Import\ImportMode;
use Multilotka\Import\UploadedFileRepository;
use RuntimeException;
use Twig\Environment;
use ValueError;

final class ImportController
{
    private ImportCoordinator $coordinator;
    private UploadedFileRepository $uploads;
    private ImportJobRepository $jobs;
    private ImportJobEventRepository $events;
    private string $projectRoot;

    public function __construct(
        Environment $twig,
        private readonly Connection $connection,
        private readonly SessionManager $session,
    ) {
        $this->uploads = new UploadedFileRepository($connection);
        $this->jobs = new ImportJobRepository($connection);
        $this->events = new ImportJobEventRepository($connection);
        $this->projectRoot = dirname(__DIR__, 2);
        $this->coordinator = new ImportCoordinator(
            $this->uploads,
            $this->jobs,
            $this->events,
            $this->projectRoot,
        );
    }

    public function trigger(): RedirectResponse
    {
        if (!$this->session->has('user_id')) {
            $this->session->flash('error', 'Zaloguj się, aby uruchomić import.');

            return new RedirectResponse('/login');
        }

        $modeValue = (string) ($_POST['mode'] ?? ImportMode::FULL->value);
        try {
            $mode = ImportMode::from($modeValue);
        } catch (ValueError) {
            $this->session->flash('error', 'Nieprawidłowy tryb importu.');

            return new RedirectResponse('/dashboard');
        }

        $fileId = isset($_POST['file_id']) ? (int) $_POST['file_id'] : 0;
        if ($fileId <= 0) {
            $latest = $this->uploads->latestValidated();
            if ($latest === null || $latest->id() === null) {
                $this->session->flash('error', 'Brak pliku do importu. Wgraj plik TXT przed uruchomieniem ETL.');

                return new RedirectResponse('/dashboard');
            }

            $fileId = $latest->id();
        }

        try {
            $job = $this->coordinator->dispatch(
                $fileId,
                $mode,
                (int) $this->session->get('user_id'),
            );

            $this->session->set('last_import_mode', $mode->value);

            $message = sprintf(
                'Import #%d został uruchomiony (tryb: %s).',
                $job->id(),
                $mode === ImportMode::FULL ? 'pełny' : 'przyrostowy',
            );
            $this->session->flash('success_import', $message);
        } catch (RuntimeException $exception) {
            $this->session->flash('error_import', $exception->getMessage());
        } catch (DBALException|JsonException $exception) {
            $this->session->flash('error_import', 'Nie udało się uruchomić importu. Spróbuj ponownie później.');
        }

        return new RedirectResponse('/dashboard');
    }

    public function status(): string
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->session->has('user_id')) {
            http_response_code(401);

            return json_encode(['error' => 'Brak autoryzacji.'], JSON_UNESCAPED_UNICODE);
        }

        $fileId = isset($_GET['file_id']) ? (int) $_GET['file_id'] : 0;

        if ($fileId <= 0) {
            $latest = $this->uploads->latestValidated();
            $fileId = $latest?->id() ?? 0;
        }

        if ($fileId <= 0) {
            return json_encode([
                'job' => null,
                'events' => [],
            ], JSON_UNESCAPED_UNICODE);
        }

        try {
            $job = $this->jobs->findLatestForFile($fileId);
            if ($job === null || $job->id() === null) {
                return json_encode([
                    'job' => null,
                    'events' => [],
                ], JSON_UNESCAPED_UNICODE);
            }

            $events = $this->events->findRecentForJob($job->id(), 3);

            return json_encode([
                'job' => ImportFormatter::formatJob($job),
                'events' => array_map(
                    static fn (ImportJobEvent $event): array => ImportFormatter::formatEvent($event),
                    $events,
                ),
            ], JSON_UNESCAPED_UNICODE);
        } catch (DBALException|JsonException $exception) {
            http_response_code(500);

            return json_encode([
                'error' => 'Nie udało się pobrać statusu importu.',
                'details' => $exception->getMessage(),
            ], JSON_UNESCAPED_UNICODE);
        }
    }
}
