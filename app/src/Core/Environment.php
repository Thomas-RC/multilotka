<?php

declare(strict_types=1);

namespace Multilotka\Core;

final class Environment
{
    private const BOOTSTRAPPED_FLAG = '__multilotka_env_bootstrapped';

    public static function bootstrap(?string $path = null): void
    {
        if (isset($_ENV[self::BOOTSTRAPPED_FLAG])) {
            return;
        }

        $envPath = $path ?? dirname(__DIR__, 2) . '/.env';

        if (is_string($envPath) && is_readable($envPath)) {
            $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            if ($lines !== false) {
                foreach ($lines as $line) {
                    $trimmed = trim($line);

                    if ($trimmed === '' || $trimmed[0] === '#') {
                        continue;
                    }

                    [$key, $value] = self::parseLine($trimmed);

                    if ($key === null) {
                        continue;
                    }

                    if (self::get($key) !== null) {
                        continue;
                    }

                    putenv($key . '=' . $value);
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }

        $_ENV[self::BOOTSTRAPPED_FLAG] = '1';
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_ENV)) {
            return $_ENV[$key];
        }

        if (array_key_exists($key, $_SERVER)) {
            return $_SERVER[$key];
        }

        $value = getenv($key);

        return $value === false ? $default : $value;
    }

    /**
     * @return array{0: string|null, 1: string}
     */
    private static function parseLine(string $line): array
    {
        $delimiterPosition = strpos($line, '=');

        if ($delimiterPosition === false) {
            return [null, ''];
        }

        $key = trim(substr($line, 0, $delimiterPosition));
        $value = trim(substr($line, $delimiterPosition + 1));

        if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
            $quote = $value[0];
            $value = trim($value, $quote);
        }

        return [$key !== '' ? $key : null, $value];
    }
}
