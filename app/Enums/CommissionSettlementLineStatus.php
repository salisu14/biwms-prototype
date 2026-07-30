<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionSettlementLineStatus: string
{
    case Prepared = 'prepared';
    case Held = 'held';
    case Excluded = 'excluded';
    case Approved = 'approved';
    case Locked = 'locked';
    case Cancelled = 'cancelled';
    case Superseded = 'superseded';
}
