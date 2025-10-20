<?php

declare(strict_types=1);

namespace Multilotka\Import;

enum ImportJobEventType: string
{
    case QUEUED = 'queued';
    case CONNECTION_ESTABLISHED = 'connection_established';
    case BATCH_INSERTED = 'batch_inserted';
    case PROGRESS_UPDATE = 'progress_update';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case CANCELLED = 'cancelled';
}
