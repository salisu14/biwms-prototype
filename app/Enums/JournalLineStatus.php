<?php

declare(strict_types=1);

namespace App\Enums;

enum JournalLineStatus: string
{
    case OPEN = 'open';
    case CHECKED = 'checked';
    case REJECTED = 'rejected';
    case POSTED = 'posted';
    case ACTIVE = 'active'; // For recurring
    case EXPIRED = 'expired'; // For recurring
    case ON_HOLD = 'on_hold'; // For recurring
}
