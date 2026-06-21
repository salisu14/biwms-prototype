<?php

declare(strict_types=1);

namespace App\Enums;

enum JournalBatchStatus: string
{
    case OPEN = 'open';
    case RELEASED = 'released';
    case POSTED = 'posted';
    case CANCELLED = 'cancelled';
    case PROCESSING = 'processing'; // For recurring
}
