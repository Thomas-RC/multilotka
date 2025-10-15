<?php

declare(strict_types=1);

namespace Multilotka\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception as DBALException;
use Multilotka\Core\RedirectResponse;
use Multilotka\Core\SessionManager;
use Multilotka\Import\UploadService;
use Multilotka\Import\UploadValidationException;
use Multilotka\Import\UploadedFileRepository;
use Multilotka\Import\UploadedFileValidator;
use Throwable;
use Twig\Environment;

final class UploadController
{
    public function __construct(
        Environment $twig,
        private readonly Connection $connection,
        private readonly SessionManager $session,
    ) {
    }

    public function handle(): RedirectResponse
    {
        if (!$this->session->has('user_id')) {
            $this->session->flash('error', 'Zaloguj się, aby wgrywać pliki.');

            return new RedirectResponse('/login');
        }

        if (!isset($_FILES['drawFile'])) {
            $this->session->flash('error', 'Nie wybrano pliku do przesłania.');

            return new RedirectResponse('/dashboard');
        }

        $repository = new UploadedFileRepository($this->connection);
        $validator = new UploadedFileValidator();
        $projectRoot = dirname(__DIR__, 2);
        $service = new UploadService($repository, $validator, $projectRoot);

        try {
            $saved = $service->handle($_FILES['drawFile'], (int) $this->session->get('user_id'));

            $start = $saved->drawDateStart()?->format('d.m.Y') ?? 'brak danych';
            $end = $saved->drawDateEnd()?->format('d.m.Y') ?? 'brak danych';

            $this->session->flash(
                'success',
                sprintf(
                    'Plik "%s" został wgrany (%d losowań, zakres %s – %s).',
                    $saved->originalName(),
                    $saved->rowsTotal(),
                    $start,
                    $end,
                ),
            );
        } catch (UploadValidationException $exception) {
            $this->session->flash('error', $exception->getMessage());
        } catch (DBALException) {
            $this->session->flash('error', 'Nie udało się zapisać metadanych pliku. Spróbuj ponownie później.');
        } catch (Throwable) {
            $this->session->flash('error', 'Wystąpił nieoczekiwany błąd podczas wgrywania pliku.');
        }

        return new RedirectResponse('/dashboard');
    }
}
