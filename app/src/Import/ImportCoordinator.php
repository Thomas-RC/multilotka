<?php

declare(strict_types=1);

namespace Multilotka\Import;

use DateTimeImmutable;
use Doctrine\DBAL\Exception as DBALException;
use JsonException;
use RuntimeException;

final class ImportCoordinator
{
    public function __construct(
        private readonly UploadedFileRepositoryInterface $files,
        private readonly ImportJobRepository $jobs,
        private readonly ImportJobEventRepository $events,
        private readonly string $projectRoot,
    ) {
    }

    /**
     * @throws RuntimeException
     * @throws DBALException
     * @throws JsonException
     */
    public function dispatch(int $fileId, ImportMode $mode, int $userId): ImportJob
    {
        $file = $this->files->findById($fileId);
        if ($file === null || $file->id() === null) {
            throw new RuntimeException('Nie znaleziono wskazanego pliku do importu.');
        }

        $now = new DateTimeImmutable('now');
        $absolutePath = $this->projectRoot . DIRECTORY_SEPARATOR . $file->storedPath();

        $requestPayload = [
            'absolute_path' => $absolutePath,
            'relative_path' => $file->storedPath(),
            'file_sha256' => $file->sha256(),
            'mode' => $mode->value,
            'user_id' => $userId,
            'queued_at' => $now->format(DateTimeImmutable::ATOM),
        ];

        $job = ImportJob::queue($file->id(), $mode, $requestPayload, $now);
        $job = $this->jobs->create($job);

        $this->events->record(
            ImportJobEvent::create(
                $job->id(),
                ImportJobEventType::QUEUED,
                ['message' => 'Import zakolejkowany. Oczekiwanie na start procesu ETL.'],
                $now,
            ),
        );

        return $job;
    }
}
