<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionPaymentLineStatus: string
{
    case Draft = 'draft';
    case Eligible = 'eligible';
    case Held = 'held';
    case Excluded = 'excluded';
    case Approved = 'approved';
    case Posted = 'posted';
    case Failed = 'failed';
    case Reversed = 'reversed';
    case PartiallyReversed = 'partially_reversed';
}
