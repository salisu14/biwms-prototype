<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionHierarchyStatus: string
{
    case Draft = 'draft';
    case Planned = 'planned';
    case Exploded = 'exploded';
    case ChildrenGenerated = 'children_generated';
    case PartiallyReleased = 'partially_released';
    case Released = 'released';
    case InProgress = 'in_progress';
    case PartiallyCompleted = 'partially_completed';
    case Completed = 'completed';
    case Exception = 'exception';
    case Cancelled = 'cancelled';

    public function isImmutable(): bool
    {
        return in_array($this, [
            self::Released,
            self::InProgress,
            self::PartiallyCompleted,
            self::Completed,
            self::Cancelled,
        ], true);
    }
}
