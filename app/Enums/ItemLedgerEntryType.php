<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ItemLedgerEntryType: string implements HasLabel, HasColor, HasIcon
{
    case PURCHASE = 'Purchase';
    case SALE = 'Sale';
    case POSITIVE_ADJUSTMENT = 'Positive Adjmt.';
    case NEGATIVE_ADJUSTMENT = 'Negative Adjmt.';
    case TRANSFER = 'Transfer';
    case CONSUMPTION = 'Consumption';
    case OUTPUT = 'Output';
    case CAPACITY = 'Capacity';
    case ASSEMBLY_CONSUMPTION = 'Assembly Consumption';
    case ASSEMBLY_OUTPUT = 'Assembly Output';
    case OVERHEAD = 'Overhead';

    public function label(): string
    {
        return $this->value;
    }

    public function getLabel(): ?string
    {
        return $this->label();
    }

    public function color(): string
    {
        return match ($this) {
            self::PURCHASE, self::POSITIVE_ADJUSTMENT, self::OUTPUT, self::ASSEMBLY_OUTPUT => 'success',
            self::SALE, self::NEGATIVE_ADJUSTMENT, self::CONSUMPTION, self::ASSEMBLY_CONSUMPTION => 'danger',
            self::TRANSFER, self::CAPACITY, self::OVERHEAD => 'info',
        };
    }

    public function getColor(): string|array|null
    {
        return $this->color();
    }

    public function icon(): string
    {
        return match ($this) {
            self::PURCHASE => 'heroicon-o-shopping-cart',
            self::SALE => 'heroicon-o-tag',
            self::POSITIVE_ADJUSTMENT => 'heroicon-o-plus-circle',
            self::NEGATIVE_ADJUSTMENT => 'heroicon-o-minus-circle',
            self::TRANSFER => 'heroicon-o-arrows-right-left',
            self::CONSUMPTION => 'heroicon-o-fire',
            self::OUTPUT => 'heroicon-o-beaker',
            self::CAPACITY => 'heroicon-o-cog',
            self::ASSEMBLY_CONSUMPTION => 'heroicon-o-puzzle-piece',
            self::ASSEMBLY_OUTPUT => 'heroicon-o-gift',
            self::OVERHEAD => 'heroicon-o-chart-bar',
        };
    }

    public function getIcon(): ?string
    {
        return $this->icon();
    }

    public function isProduction(): bool
    {
        return in_array($this, [
            self::CONSUMPTION,
            self::OUTPUT,
            self::CAPACITY,
            self::OVERHEAD,
        ]);
    }
}
