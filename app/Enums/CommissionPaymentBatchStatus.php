<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionPaymentBatchStatus: string
{
    case Draft = 'draft';
    case Prepared = 'prepared';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Posted = 'posted';
    case Failed = 'failed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case PartiallyReversed = 'partially_reversed';
    case Reversed = 'reversed';
}
