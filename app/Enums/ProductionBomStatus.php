<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ProductionBomStatus: string implements HasColor, HasIcon, HasLabel
{
    case NEW = 'NEW';
    case UNDER_DEVELOPMENT = 'UNDER_DEVELOPMENT';
    case CERTIFIED = 'CERTIFIED';
    case CLOSED = 'CLOSED';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::NEW => 'New',
            self::UNDER_DEVELOPMENT => 'Under Development',
            self::CERTIFIED => 'Certified (Active)',
            self::CLOSED => 'Closed / Obsolete',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::NEW => 'gray',
            self::UNDER_DEVELOPMENT => 'warning',
            self::CERTIFIED => 'success',
            self::CLOSED => 'danger',
        };
    }

    public function getIcon(): ?string
    {
        return match ($this) {
            self::NEW => 'heroicon-m-sparkles',
            self::UNDER_DEVELOPMENT => 'heroicon-m-wrench-screwdriver',
            self::CERTIFIED => 'heroicon-m-check-badge',
            self::CLOSED => 'heroicon-m-archive-box',
        };
    }
}
