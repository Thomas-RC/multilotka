<?php

declare(strict_types=1);

namespace Multilotka\Import;

enum ImportMode: string
{
    case FULL = 'full';
    case INCREMENTAL = 'incremental';
}
