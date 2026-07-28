<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionOutputAllocationStatus: string
{
    case Pending = 'pending';
    case Provisional = 'provisional';
    case Final = 'final';
    case Reversed = 'reversed';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Pending => in_array($next, [self::Pending, self::Provisional, self::Final, self::Reversed], true),
            self::Provisional => in_array($next, [self::Provisional, self::Final, self::Reversed], true),
            self::Final => in_array($next, [self::Final, self::Reversed], true),
            self::Reversed => $next === self::Reversed,
        };
    }
}
