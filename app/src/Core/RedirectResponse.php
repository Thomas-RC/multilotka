<?php

declare(strict_types=1);

namespace Multilotka\Core;

final class RedirectResponse
{
    public function __construct(
        private readonly string $location,
        private readonly int $status = 302,
    ) {
    }

    public function send(): never
    {
        header('Location: ' . $this->location, true, $this->status);
        exit;
    }

    public function location(): string
    {
        return $this->location;
    }

    public function status(): int
    {
        return $this->status;
    }
}
