<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionReviewLineStatus: string
{
    case Pending = 'pending';
    case Eligible = 'eligible';
    case Held = 'held';
    case Disputed = 'disputed';
    case Rejected = 'rejected';
    case Forfeited = 'forfeited';
    case Approved = 'approved';
    case Excluded = 'excluded';
    case Superseded = 'superseded';
}
