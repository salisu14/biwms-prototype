<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum PriceAdjustmentType: string implements HasColor, HasIcon, HasLabel
{
    case INCREASE = 'increase';
    case DECREASE = 'decrease';
    case FIXED = 'fixed';

    /**
     * Returns the label used in Filament Selects and Tables.
     */
    public function getLabel(): ?string
    {
        return match ($this) {
            self::INCREASE => 'Price Increase',
            self::DECREASE => 'Discount / Decrease',
            self::FIXED => 'Fixed Price Overwrite',
        };
    }

    /**
     * Returns the color for Filament Badges and Icons.
     */
    public function getColor(): string|array|null
    {
        return match ($this) {
            self::INCREASE => 'warning',
            self::DECREASE => 'success',
            self::FIXED => 'info',
        };
    }

    /**
     * Returns a relevant icon for the UI.
     */
    public function getIcon(): ?string
    {
        return match ($this) {
            self::INCREASE => 'heroicon-m-arrow-trending-up',
            self::DECREASE => 'heroicon-m-arrow-trending-down',
            self::FIXED => 'heroicon-m-equals',
        };
    }

    /**
     * Business logic: Calculate the new price based on the adjustment type.
     */
    public function calculate(float $basePrice, float $adjustmentValue): float
    {
        return match ($this) {
            self::INCREASE => $basePrice + $adjustmentValue,
            self::DECREASE => max(0, $basePrice - $adjustmentValue),
            self::FIXED => $adjustmentValue,
        };
    }

    /**
     * Useful for checking if the adjustment is relative to the base price.
     */
    public function isRelative(): bool
    {
        return $this !== self::FIXED;
    }
}
