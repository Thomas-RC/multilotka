<?php

declare(strict_types=1);

namespace Multilotka\Import;

use DateTimeImmutable;
use RuntimeException;

final class UploadService
{
    /**
     * @var array<int, string>
     */
    private const ALLOWED_MIME_TYPES = [
        'text/plain',
        'text/csv',
        'application/csv',
    ];

    public function __construct(
        private readonly UploadedFileRepositoryInterface $repository,
        private readonly UploadedFileValidator $validator,
        private readonly string $projectRoot,
    ) {
    }

    /**
     * @param array<string, mixed> $uploadedFile
     */
    public function handle(array $uploadedFile, int $userId): UploadedFileRecord
    {
        $errorCode = (int) ($uploadedFile['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($errorCode !== UPLOAD_ERR_OK) {
            throw UploadValidationException::withMessage($this->translateUploadError($errorCode));
        }

        $tmpPath = (string) ($uploadedFile['tmp_name'] ?? '');
        if ($tmpPath === '' || !is_file($tmpPath)) {
            throw UploadValidationException::withMessage('Nieprawidłowy plik przesłany do walidacji.');
        }

        $originalName = (string) ($uploadedFile['name'] ?? 'losowania.txt');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== 'txt') {
            throw UploadValidationException::withMessage('Dozwolone są wyłącznie pliki z rozszerzeniem .txt.');
        }

        $mimeType = $this->detectMimeType($tmpPath);
        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw UploadValidationException::withMessage(sprintf('Nieobsługiwany typ pliku (%s).', $mimeType));
        }

        $metadata = $this->validator->validate($tmpPath);
        $sha256 = hash_file('sha256', $tmpPath);

        if ($sha256 === false) {
            throw new RuntimeException('Nie udało się obliczyć sumy kontrolnej pliku.');
        }

        $previous = $this->repository->latestValidated();

        $filename = $this->generateFilename($sha256);
        $relativePath = 'storage/uploads/' . $filename;
        $absolutePath = $this->projectRoot . DIRECTORY_SEPARATOR . $relativePath;

        $this->ensureDirectory(dirname($absolutePath));
        $this->storeFile($tmpPath, $absolutePath);

        $record = UploadedFileRecord::validated(
            $userId,
            $originalName,
            $relativePath,
            $sha256,
            $metadata,
            new DateTimeImmutable('now'),
        );

        $saved = $this->repository->save($record);

        if ($previous !== null && $previous->id() !== null) {
            $this->cleanupPrevious($previous);
        }

        return $saved;
    }

    private function translateUploadError(int $errorCode): string
    {
        return match ($errorCode) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Przesłany plik jest zbyt duży.',
            UPLOAD_ERR_PARTIAL => 'Plik został przesłany tylko częściowo.',
            UPLOAD_ERR_NO_FILE => 'Nie wybrano pliku do przesłania.',
            UPLOAD_ERR_NO_TMP_DIR => 'Brak katalogu tymczasowego na serwerze.',
            UPLOAD_ERR_CANT_WRITE => 'Nie udało się zapisać pliku na dysku.',
            UPLOAD_ERR_EXTENSION => 'Rozszerzenie PHP zablokowało przesyłanie pliku.',
            default => 'Wystąpił nieznany błąd podczas przesyłania pliku.',
        };
    }

    private function detectMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($finfo === false) {
            return 'application/octet-stream';
        }

        $mimeType = finfo_file($finfo, $path);
        finfo_close($finfo);

        return $mimeType !== false ? $mimeType : 'text/plain';
    }

    private function generateFilename(string $sha256): string
    {
        $timestamp = (new DateTimeImmutable('now'))->format('Ymd_His');
        $hashPrefix = substr($sha256, 0, 8);

        return sprintf('ml_%s_%s.txt', $timestamp, $hashPrefix);
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            if (!@mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new RuntimeException(sprintf('Nie udało się utworzyć katalogu %s. Sprawdź uprawnienia do zapisu.', $directory));
            }
        }
    }

    private function storeFile(string $source, string $destination): void
    {
        if (@move_uploaded_file($source, $destination)) {
            return;
        }

        if (@rename($source, $destination)) {
            return;
        }

        if (!@copy($source, $destination)) {
            throw new RuntimeException('Nie udało się zapisać pliku w katalogu docelowym.');
        }

        @unlink($source);
    }

    private function cleanupPrevious(UploadedFileRecord $previous): void
    {
        $absolutePath = $this->projectRoot . DIRECTORY_SEPARATOR . $previous->storedPath();

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }

        $this->repository->markAsArchived((int) $previous->id());
    }
}
