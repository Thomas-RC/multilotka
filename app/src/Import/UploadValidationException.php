<?php

declare(strict_types=1);

namespace Multilotka\Import;

use RuntimeException;

final class UploadValidationException extends RuntimeException
{
    public static function withMessage(string $message): self
    {
        return new self($message);
    }
}
