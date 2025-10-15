<?php

declare(strict_types=1);

namespace Multilotka\Tests\Import;

use Multilotka\Import\UploadValidationException;
use Multilotka\Import\UploadedFileValidator;
use PHPUnit\Framework\TestCase;

final class UploadedFileValidatorTest extends TestCase
{
    /** @var array<int, string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        $this->tempFiles = [];
    }

    public function testValidateReturnsMetadataForCorrectFile(): void
    {
        $content = implode(PHP_EOL, [
            '1. 01.01.2024 ' . $this->numbersRange(1, 20),
            '2. 03.01.2024 ' . $this->numbersRange(21, 40),
        ]) . PHP_EOL;

        $path = $this->createTempFile($content);
        $validator = new UploadedFileValidator();

        $metadata = $validator->validate($path);

        self::assertSame(2, $metadata->rowsTotal());
        self::assertSame('2024-01-01', $metadata->drawDateStart()->format('Y-m-d'));
        self::assertSame('2024-01-03', $metadata->drawDateEnd()->format('Y-m-d'));
    }

    public function testValidateThrowsForInvalidNumberCount(): void
    {
        $content = '1. 01.01.2024 1,2,3';

        $path = $this->createTempFile($content);
        $validator = new UploadedFileValidator();

        $this->expectException(UploadValidationException::class);
        $this->expectExceptionMessage('Linia 1');

        $validator->validate($path);
    }

    public function testValidateSupportsHistoricPolishFormat(): void
    {
        $content = implode(PHP_EOL, [
            '1. 18.03.1996 4,9,10,16,21,22,23,26,27,34,35,41,42,48,62,66,68,73,76,78',
            '2. 19.03.1996 6,12,15,19,28,33,35,39,44,48,49,59,62,63,64,67,69,71,75,77',
            '3. 21.03.1996 2,4,6,7,15,16,17,19,20,26,28,45,48,52,54,69,72,73,75,77',
            '4. 22.03.1996 3,8,15,17,19,25,30,33,34,35,36,38,44,49,60,61,64,67,68,75',
            '5. 25.03.1996 2,10,11,14,18,22,26,27,29,30,42,44,45,55,60,61,66,67,75,79',
        ]) . PHP_EOL;

        $path = $this->createTempFile($content);
        $validator = new UploadedFileValidator();

        $metadata = $validator->validate($path);

        self::assertSame(5, $metadata->rowsTotal());
        self::assertSame('1996-03-18', $metadata->drawDateStart()->format('Y-m-d'));
        self::assertSame('1996-03-25', $metadata->drawDateEnd()->format('Y-m-d'));
    }

    private function createTempFile(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mlt');
        if ($path === false) {
            self::fail('Nie udało się utworzyć pliku tymczasowego.');
        }

        file_put_contents($path, $content);
        $this->tempFiles[] = $path;

        return $path;
    }

    private function numbersRange(int $start, int $end): string
    {
        $numbers = [];
        for ($i = $start; $i <= $end; $i++) {
            $numbers[] = (string) $i;
        }

        return implode(',', $numbers);
    }
}
