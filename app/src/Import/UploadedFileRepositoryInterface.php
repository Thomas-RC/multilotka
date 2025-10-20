<?php

declare(strict_types=1);

namespace Multilotka\Import;

interface UploadedFileRepositoryInterface
{
    public function latestValidated(): ?UploadedFileRecord;

    public function findById(int $id): ?UploadedFileRecord;

    public function save(UploadedFileRecord $record): UploadedFileRecord;

    public function markAsArchived(int $id): void;
}
