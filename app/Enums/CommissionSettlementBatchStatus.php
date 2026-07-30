<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionSettlementBatchStatus: string
{
    case Draft = 'draft';
    case Prepared = 'prepared';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Locked = 'locked';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Superseded = 'superseded';
}
