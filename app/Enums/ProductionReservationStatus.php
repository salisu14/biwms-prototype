<?php

declare(strict_types=1);

namespace App\Enums;

enum ProductionReservationStatus: string
{
    case Active = 'active';
    case PartiallyConsumed = 'partially_consumed';
    case Consumed = 'consumed';
    case Released = 'released';
    case Cancelled = 'cancelled';
    case Expired = 'expired';

    public function isImmutable(): bool
    {
        return in_array($this, [
            self::Consumed,
            self::Released,
            self::Cancelled,
            self::Expired,
        ], true);
    }
}
