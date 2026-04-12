<?php

declare(strict_types=1);

namespace App\Enums;

enum RecurringMethod: string
{
    case FIXED = 'fixed';
    case VARIABLE = 'variable';
    case BALANCE = 'balance';
    case REVERSING_FIXED = 'reversing_fixed';
    case REVERSING_VARIABLE = 'reversing_variable';
    case REVERSING_BALANCE = 'reversing_balance';

    public function isReversing(): bool
    {
        return str_starts_with($this->value, 'reversing_');
    }

    public function requiresCalculation(): bool
    {
        return in_array($this, [self::VARIABLE, self::BALANCE, self::REVERSING_VARIABLE, self::REVERSING_BALANCE]);
    }
}
