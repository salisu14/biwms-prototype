<?php

declare(strict_types=1);

namespace App\Enums;

enum DepreciationMethod: string
{
    case STRAIGHT_LINE = 'straight_line';           // Equal amounts per period
    case DECLINING_BALANCE = 'declining_balance';   // % of remaining book value
    case DOUBLE_DECLINING = 'double_declining';     // 2x straight-line rate
    case REDUCING_BALANCE = 'reducing_balance';     // Same as declining
    case UNITS_OF_PRODUCTION = 'units_of_production'; // Based on usage/output
    case SUM_OF_YEARS_DIGITS = 'sum_of_years_digits'; // Accelerated, front-loaded
    case MANUAL = 'manual';                          // User-entered amounts
    case NONE = 'none';                              // Non-depreciable assets

    public function isAccelerated(): bool
    {
        return in_array($this, [
            self::DECLINING_BALANCE,
            self::DOUBLE_DECLINING,
            self::SUM_OF_YEARS_DIGITS,
        ]);
    }

    public function requiresUsageData(): bool
    {
        return $this === self::UNITS_OF_PRODUCTION;
    }
}
