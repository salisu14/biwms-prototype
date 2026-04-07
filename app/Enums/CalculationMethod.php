<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CalculationMethod: string implements HasLabel
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed Amount',
            self::Percentage => 'Percentage (%)',
        };
    }

    public function getSymbol(): string
    {
        return match ($this) {
            self::Fixed => '$',
            self::Percentage => '%',
        };
    }

    /**
     * Apply the calculation to a base value
     */
    public function calculate(float $base, float $value): float
    {
        return match ($this) {
            self::Fixed => $value,
            self::Percentage => ($base * ($value / 100)),
        };
    }
}
