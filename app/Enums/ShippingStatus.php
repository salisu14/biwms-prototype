<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ShippingStatus: string implements HasColor, HasIcon, HasLabel
{
    case Pending = 'pending';
    case Picked = 'picked';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pending => 'Pending Pick',
            self::Picked => 'Picked',
            self::Packed => 'Packed & Ready',
            self::Shipped => 'In Transit',
            self::Delivered => 'Delivered',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Picked => 'info',
            self::Packed => 'warning',
            self::Shipped => 'primary',
            self::Delivered => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Pending => 'heroicon-m-clock',
            self::Picked => 'heroicon-m-hand-raised',
            self::Packed => 'heroicon-m-archive-box',
            self::Shipped => 'heroicon-m-truck',
            self::Delivered => 'heroicon-m-check-circle',
        };
    }

    public function isShipped(): bool
    {
        return in_array($this, [self::Shipped, self::Delivered]);
    }
}
