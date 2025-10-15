<?php

declare(strict_types=1);

namespace Multilotka\Import;

use DateTimeImmutable;

final class UploadedFileValidator
{
    private const EXPECTED_NUMBERS = 20;
    private const NUMBER_MIN = 1;
    private const NUMBER_MAX = 80;

    public function validate(string $path): UploadedFileMetadata
    {
        if (!is_file($path) || !is_readable($path)) {
            throw UploadValidationException::withMessage('Nie udało się odczytać przesłanego pliku.');
        }

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw UploadValidationException::withMessage('Nie udało się otworzyć pliku do weryfikacji.');
        }

        $rows = 0;
        $lineNumber = 0;
        $minDate = null;
        $maxDate = null;

        try {
            while (($line = fgets($handle)) !== false) {
                $lineNumber++;
                $trimmed = trim($line);

                if ($trimmed === '') {
                    throw UploadValidationException::withMessage(sprintf('Linia %d: plik zawiera pustą linię.', $lineNumber));
                }

                [$drawNumberRaw, $drawDateRaw, $numbersRaw] = $this->splitLine($trimmed, $lineNumber);

                if (!preg_match('/^\d+$/', $drawNumberRaw)) {
                    throw UploadValidationException::withMessage(sprintf('Linia %d: numer losowania musi być liczbą całkowitą.', $lineNumber));
                }

                $drawDate = $this->parseDrawDate($drawDateRaw, $lineNumber);
                $numbers = $this->parseNumbers($numbersRaw, $lineNumber);

                $rows++;
                $minDate ??= $drawDate;
                $maxDate ??= $drawDate;
                if ($drawDate < $minDate) {
                    $minDate = $drawDate;
                }
                if ($drawDate > $maxDate) {
                    $maxDate = $drawDate;
                }
            }
        } finally {
            fclose($handle);
        }

        if ($lineNumber === 0 || $rows === 0 || $minDate === null || $maxDate === null) {
            throw UploadValidationException::withMessage('Plik nie zawiera żadnych losowań.');
        }

        return new UploadedFileMetadata($rows, $minDate, $maxDate);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function splitLine(string $trimmed, int $lineNumber): array
    {
        $pattern = '/^\s*(\d+)\.\s+(?<date>\d{2}\.\d{2}\.\d{4})\s+(?<numbers>\d+(?:\s*,\s*\d+)*)\s*$/';
        if (preg_match($pattern, $trimmed, $matches) === 1) {
            return [$matches[1], $matches['date'], $matches['numbers']];
        }

        throw UploadValidationException::withMessage(sprintf('Linia %d: nie rozpoznano formatu wiersza.', $lineNumber));
    }

    private function parseDrawDate(string $rawDate, int $lineNumber): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('d.m.Y', $rawDate);
        if ($date !== false) {
            $errors = DateTimeImmutable::getLastErrors();
            if (($errors['warning_count'] ?? 0) === 0 && ($errors['error_count'] ?? 0) === 0) {
                return $date;
            }
        }

        throw UploadValidationException::withMessage(sprintf('Linia %d: niepoprawny format daty, oczekiwano DD.MM.YYYY.', $lineNumber));
    }

    /**
     * @return array<int, int>
     */
    private function parseNumbers(string $numbersRaw, int $lineNumber): array
    {
        $numbers = array_map(static fn (string $number): string => trim($number), explode(',', $numbersRaw));

        if (count($numbers) !== self::EXPECTED_NUMBERS) {
            throw UploadValidationException::withMessage(sprintf('Linia %d: oczekiwano %d liczb w losowaniu.', $lineNumber, self::EXPECTED_NUMBERS));
        }

        $unique = [];
        foreach ($numbers as $numberRaw) {
            if (!preg_match('/^\d+$/', $numberRaw)) {
                throw UploadValidationException::withMessage(sprintf('Linia %d: wszystkie wartości muszą być liczbami całkowitymi.', $lineNumber));
            }

            $value = (int) $numberRaw;
            if ($value < self::NUMBER_MIN || $value > self::NUMBER_MAX) {
                throw UploadValidationException::withMessage(
                    sprintf(
                        'Linia %d: liczby muszą mieścić się w zakresie %d-%d.',
                        $lineNumber,
                        self::NUMBER_MIN,
                        self::NUMBER_MAX,
                    ),
                );
            }

            $unique[$value] = true;
        }

        if (count($unique) !== self::EXPECTED_NUMBERS) {
            throw UploadValidationException::withMessage(sprintf('Linia %d: liczby w losowaniu muszą być unikalne.', $lineNumber));
        }

        return array_map(static fn (string $value): int => (int) $value, $numbers);
    }
}
