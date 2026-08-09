<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionOperationScheduleStatus: string
{
    case Planned = 'planned';
    case Tentative = 'tentative';
    case Frozen = 'frozen';
    case Committed = 'committed';
    case Started = 'started';
    case Completed = 'completed';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case Exception = 'exception';
}
