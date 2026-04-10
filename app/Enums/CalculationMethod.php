<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CalculationMethod: string implements HasLabel
{
    case FIXED_AMOUNT = 'FIXED_AMOUNT';
    case PERCENTAGE = 'PERCENTAGE';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FIXED_AMOUNT => 'Fixed Amount',
            self::PERCENTAGE => 'Percentage of Base Salary',
        };
    }
}
