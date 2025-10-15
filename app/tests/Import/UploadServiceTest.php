<?php

declare(strict_types=1);

namespace Multilotka\Tests\Import;

use DateTimeImmutable;
use Multilotka\Import\UploadService;
use Multilotka\Import\UploadedFileMetadata;
use Multilotka\Import\UploadedFileRecord;
use Multilotka\Import\UploadedFileRepositoryInterface;
use Multilotka\Import\UploadedFileValidator;
use PHPUnit\Framework\TestCase;

final class UploadServiceTest extends TestCase
{
    private string $projectRoot;

    private FakeUploadedFileRepository $repository;

    private UploadedFileValidator $validator;

    protected function setUp(): void
    {
        $this->projectRoot = sys_get_temp_dir() . '/mlt_upload_' . uniqid();
        if (!mkdir($this->projectRoot, 0775, true) && !is_dir($this->projectRoot)) {
            self::fail('Nie udało się przygotować katalogu roboczego testu.');
        }

        $this->repository = new FakeUploadedFileRepository();
        $this->validator = new UploadedFileValidator();
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectRoot);
    }

    public function testHandleStoresFileAndMetadata(): void
    {
        $service = new UploadService($this->repository, $this->validator, $this->projectRoot);
        $fileInfo = $this->createUploadFixture(
            '1. 01.01.2024 ' . $this->numbersRange(1, 20) . PHP_EOL,
        );

        $record = $service->handle($fileInfo, 7);

        self::assertSame(1, $record->rowsTotal());
        self::assertSame('2024-01-01', $record->drawDateStart()?->format('Y-m-d'));
        self::assertFileExists($this->projectRoot . DIRECTORY_SEPARATOR . $record->storedPath());
        self::assertSame([], $this->repository->archivedIds());
    }

    public function testHandleAcceptsCsvMimeWithLegacyFormat(): void
    {
        $service = new UploadService($this->repository, $this->validator, $this->projectRoot);
        $fileInfo = $this->createUploadFixture(
            '1. 18.03.1996 4,9,10,16,21,22,23,26,27,34,35,41,42,48,62,66,68,73,76,78' . PHP_EOL,
            'text/csv',
        );

        $record = $service->handle($fileInfo, 3);

        self::assertSame('1996-03-18', $record->drawDateStart()?->format('Y-m-d'));
    }

    public function testHandleArchivesPreviousFile(): void
    {
        $metadata = new UploadedFileMetadata(
            1,
            new DateTimeImmutable('2024-02-01'),
            new DateTimeImmutable('2024-02-01'),
        );
        $previous = UploadedFileRecord::validated(
            7,
            'poprzedni.txt',
            'storage/uploads/poprzedni.txt',
            str_repeat('a', 64),
            $metadata,
            new DateTimeImmutable('2024-02-10 12:00:00'),
        )->withId(5);

        $this->repository->setLatest($previous);
        $this->ensureFileExists($previous->storedPath());

        $service = new UploadService($this->repository, $this->validator, $this->projectRoot);
        $fileInfo = $this->createUploadFixture(
            '2. 01.03.2024 ' . $this->numbersRange(21, 40) . PHP_EOL,
        );

        $record = $service->handle($fileInfo, 7);

        self::assertFileDoesNotExist($this->projectRoot . DIRECTORY_SEPARATOR . $previous->storedPath());
        self::assertNotSame($previous->storedPath(), $record->storedPath());
        self::assertSame([5], $this->repository->archivedIds());
    }

    /**
     * @param array<string, mixed> $fileInfo
     */
    private function ensureUploadArray(array $fileInfo): void
    {
        foreach (['tmp_name', 'name', 'type', 'error'] as $key) {
            if (!array_key_exists($key, $fileInfo)) {
                self::fail(sprintf('Brakuje pola %s w tablicy przesłanego pliku.', $key));
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function createUploadFixture(string $content, string $mimeType = 'text/plain'): array
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'mlt');
        if ($tmpFile === false) {
            self::fail('Nie udało się utworzyć pliku tymczasowego.');
        }

        file_put_contents($tmpFile, $content);

        $fileInfo = [
            'tmp_name' => $tmpFile,
            'name' => 'ml.txt',
            'type' => $mimeType,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($tmpFile),
        ];

        $this->ensureUploadArray($fileInfo);

        return $fileInfo;
    }

    private function numbersRange(int $start, int $end): string
    {
        $numbers = [];
        for ($i = $start; $i <= $end; $i++) {
            $numbers[] = (string) $i;
        }

        return implode(',', $numbers);
    }

    private function ensureFileExists(string $relativePath): void
    {
        $absolutePath = $this->projectRoot . DIRECTORY_SEPARATOR . $relativePath;
        $directory = dirname($absolutePath);

        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        file_put_contents($absolutePath, 'previous');
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }

        rmdir($directory);
    }
}

/**
 * @internal
 */
final class FakeUploadedFileRepository implements UploadedFileRepositoryInterface
{
    private ?UploadedFileRecord $latest = null;

    /** @var array<int, UploadedFileRecord> */
    private array $records = [];

    /** @var array<int, int> */
    private array $archived = [];

    public function latestValidated(): ?UploadedFileRecord
    {
        return $this->latest;
    }

    public function save(UploadedFileRecord $record): UploadedFileRecord
    {
        $id = count($this->records) + 1;
        $withId = $record->withId($id);
        $this->records[$id] = $withId;
        $this->latest = $withId;

        return $withId;
    }

    public function markAsArchived(int $id): void
    {
        $this->archived[] = $id;
    }

    public function setLatest(?UploadedFileRecord $record): void
    {
        $this->latest = $record;
        if ($record !== null && $record->id() !== null) {
            $this->records[$record->id()] = $record;
        }
    }

    /**
     * @return array<int, int>
     */
    public function archivedIds(): array
    {
        return $this->archived;
    }
}
