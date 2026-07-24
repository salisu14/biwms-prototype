<?php

declare(strict_types=1);

namespace App\Enums;

enum ReferralCommissionBasis: string
{
    case POSTED_SALES = 'POSTED_SALES';
    case PAID_SALES = 'PAID_SALES';

    public function label(): string
    {
        return match ($this) {
            self::POSTED_SALES => 'Posted Sales',
            self::PAID_SALES => 'Paid Sales',
        };
    }
}
