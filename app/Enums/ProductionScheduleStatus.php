<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionScheduleStatus: string
{
    case Draft = 'draft';
    case Generated = 'generated';
    case Reviewed = 'reviewed';
    case Approved = 'approved';
    case Superseded = 'superseded';
    case Cancelled = 'cancelled';

    public function isActive(): bool
    {
        return in_array($this, [self::Draft, self::Generated, self::Reviewed, self::Approved], true);
    }
}
