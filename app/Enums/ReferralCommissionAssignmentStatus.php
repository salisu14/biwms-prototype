<?php

declare(strict_types=1);

namespace App\Enums;

enum ReferralCommissionAssignmentStatus: string
{
    case ACTIVE = 'ACTIVE';
    case ENDED = 'ENDED';
    case CANCELLED = 'CANCELLED';

    public function label(): string
    {
        return match ($this) {
            self::ACTIVE => 'Active',
            self::ENDED => 'Ended',
            self::CANCELLED => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ACTIVE => 'success',
            self::ENDED => 'gray',
            self::CANCELLED => 'danger',
        };
    }
}
