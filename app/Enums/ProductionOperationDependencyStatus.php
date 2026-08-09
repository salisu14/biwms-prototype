<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionOperationDependencyStatus: string
{
    case Planned = 'planned';
    case Blocked = 'blocked';
    case PartiallyReady = 'partially_ready';
    case Ready = 'ready';
    case Fulfilled = 'fulfilled';
    case Cancelled = 'cancelled';
    case Invalid = 'invalid';

    public function isActive(): bool
    {
        return ! in_array($this, [self::Fulfilled, self::Cancelled, self::Invalid], true);
    }
}
