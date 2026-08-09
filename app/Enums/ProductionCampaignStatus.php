<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionCampaignStatus: string
{
    case Draft = 'draft';
    case Suggested = 'suggested';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
