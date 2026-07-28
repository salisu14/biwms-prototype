<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionCostSettlementStatus: string
{
    case NotReady = 'not_ready';
    case Pending = 'pending';
    case Settled = 'settled';
    case AdjustmentRequired = 'adjustment_required';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::NotReady => in_array($next, [self::NotReady, self::Pending, self::Settled], true),
            self::Pending => in_array($next, [self::Pending, self::Settled, self::NotReady], true),
            self::Settled => in_array($next, [self::Settled, self::AdjustmentRequired], true),
            self::AdjustmentRequired => in_array($next, [self::AdjustmentRequired, self::Settled], true),
        };
    }
}
