<?php

declare(strict_types=1);

namespace App\Enums;

enum ReferralFixedAmountApplication: string
{
    case PER_TRANSACTION = 'PER_TRANSACTION';
    case PER_INVOICE = 'PER_INVOICE';
    case PER_LINE = 'PER_LINE';
    case PER_UNIT = 'PER_UNIT';

    public function label(): string
    {
        return match ($this) {
            self::PER_TRANSACTION => 'Per Transaction',
            self::PER_INVOICE => 'Per Invoice',
            self::PER_LINE => 'Per Line',
            self::PER_UNIT => 'Per Unit',
        };
    }
}
