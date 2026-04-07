<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum SalesLineType: string implements HasLabel, HasColor, HasIcon
{
    case Item = 'item';
    case Resource = 'resource';
    case GlAccount = 'gl_account';
    case FixedAsset = 'fixed_asset';
    case ChargeItem = 'charge_item';

    public function getLabel(): string
    {
        return match ($this) {
            self::Item => 'Item',
            self::Resource => 'Resource',
            self::GlAccount => 'G/L Account',
            self::FixedAsset => 'Fixed Asset',
            self::ChargeItem => 'Charge (Item)',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Item => 'info',
            self::Resource => 'success',
            self::GlAccount => 'warning',
            self::FixedAsset => 'gray',
            self::ChargeItem => 'primary',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::Item => 'heroicon-m-cube',
            self::Resource => 'heroicon-m-user',
            self::GlAccount => 'heroicon-m-calculator',
            self::FixedAsset => 'heroicon-m-building-office',
            self::ChargeItem => 'heroicon-m-receipt-percent',
        };
    }
}
