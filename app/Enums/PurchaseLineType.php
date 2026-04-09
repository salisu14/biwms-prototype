<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseLineType: string implements HasColor, HasLabel
{
    case ITEM = 'item';
    case GL_ACCOUNT = 'gl_account';
    case RESOURCE = 'resource';
    case FIXED_ASSET = 'fixed_asset';
    case CHARGE = 'charge';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ITEM => 'Item',
            self::GL_ACCOUNT => 'G/L Account',
            self::RESOURCE => 'Resource',
            self::FIXED_ASSET => 'Fixed Asset',
            self::CHARGE => 'Charge (Item)',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::ITEM => 'success',
            self::GL_ACCOUNT => 'info',
            self::RESOURCE => 'warning',
            self::FIXED_ASSET => 'primary',
            self::CHARGE => 'gray',
        };
    }

    /**
     * Helper to return all values for validation rules
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
