<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Enum for Shipping Methods
 */
enum ShippingMethod: string implements HasLabel
{
    case Ground = 'GROUND';
    case Express = 'EXPRESS';
    case Overnight = 'OVERNIGHT';
    case Pickup = 'PICKUP';
    case Freight = 'FREIGHT';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Ground => 'Ground Shipping',
            self::Express => 'Express',
            self::Overnight => 'Overnight',
            self::Pickup => 'Customer Pickup',
            self::Freight => 'Freight/LTL',
        };
    }
}
