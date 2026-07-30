<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionReviewBatchStatus: string
{
    case Draft = 'draft';
    case Generated = 'generated';
    case UnderReview = 'under_review';
    case Submitted = 'submitted';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Locked = 'locked';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';
}
