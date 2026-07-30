<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionDisputeStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case AwaitingInformation = 'awaiting_information';
    case Upheld = 'upheld';
    case PartiallyUpheld = 'partially_upheld';
    case Rejected = 'rejected';
    case Withdrawn = 'withdrawn';
    case Closed = 'closed';
}
