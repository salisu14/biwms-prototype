<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionReviewPeriodStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case UnderReview = 'under_review';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Locked = 'locked';
    case Reopened = 'reopened';
    case Cancelled = 'cancelled';
}
