<?php

declare(strict_types=1);

namespace Multilotka\Import;

use DateTimeImmutable;

final class UploadedFileMetadata
{
    public function __construct(
        private readonly int $rowsTotal,
        private readonly DateTimeImmutable $drawDateStart,
        private readonly DateTimeImmutable $drawDateEnd,
    ) {
    }

    public function rowsTotal(): int
    {
        return $this->rowsTotal;
    }

    public function drawDateStart(): DateTimeImmutable
    {
        return $this->drawDateStart;
    }

    public function drawDateEnd(): DateTimeImmutable
    {
        return $this->drawDateEnd;
    }
}
