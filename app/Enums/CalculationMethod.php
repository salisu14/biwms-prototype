<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CalculationMethod: string implements HasLabel
{
    case FIXED_AMOUNT = 'FIXED_AMOUNT';
    case PERCENTAGE = 'PERCENTAGE';
    case HOURLY = 'HOURLY';
    case FORMULA = 'FORMULA';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::FIXED_AMOUNT => 'Fixed',
            self::PERCENTAGE => 'Percentage',
            self::HOURLY => 'Hourly',
            self::FORMULA => 'Formula',
        };
    }
}
