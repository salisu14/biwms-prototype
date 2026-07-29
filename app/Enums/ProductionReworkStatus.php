<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionReworkStatus: string
{
    case Identified = 'identified';
    case Approved = 'approved';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
    case Reversed = 'reversed';
}
