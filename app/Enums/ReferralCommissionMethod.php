<?php

declare(strict_types=1);

namespace App\Enums;

enum ReferralCommissionMethod: string
{
    case PERCENTAGE = 'PERCENTAGE';
    case FIXED_AMOUNT = 'FIXED_AMOUNT';
    case TIERED_PERCENTAGE = 'TIERED_PERCENTAGE';
    case TIERED_FIXED_AMOUNT = 'TIERED_FIXED_AMOUNT';

    public function label(): string
    {
        return match ($this) {
            self::PERCENTAGE => 'Percentage',
            self::FIXED_AMOUNT => 'Fixed Amount',
            self::TIERED_PERCENTAGE => 'Tiered Percentage',
            self::TIERED_FIXED_AMOUNT => 'Tiered Fixed Amount',
        };
    }

    public function isTiered(): bool
    {
        return in_array($this, [self::TIERED_PERCENTAGE, self::TIERED_FIXED_AMOUNT], true);
    }

    public function isPercentage(): bool
    {
        return in_array($this, [self::PERCENTAGE, self::TIERED_PERCENTAGE], true);
    }

    public function isFixedAmount(): bool
    {
        return in_array($this, [self::FIXED_AMOUNT, self::TIERED_FIXED_AMOUNT], true);
    }
}
