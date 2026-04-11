<?php

namespace App\Enums;

enum JournalEntryType: string
{
    case PURCHASE = 'PURCHASE';
    case SALE = 'SALE';
    case POSITIVE_ADJUSTMENT = 'POSITIVE_ADJUSTMENT';
    case NEGATIVE_ADJUSTMENT = 'NEGATIVE_ADJUSTMENT';
    case TRANSFER = 'TRANSFER';
    case CONSUMPTION = 'CONSUMPTION';
    case OUTPUT = 'OUTPUT';
    case ASSEMBLY_CONSUMPTION = 'ASSEMBLY_CONSUMPTION';
    case ASSEMBLY_OUTPUT = 'ASSEMBLY_OUTPUT';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase',
            self::SALE => 'Sale',
            self::POSITIVE_ADJUSTMENT => 'Positive Adjustment',
            self::NEGATIVE_ADJUSTMENT => 'Negative Adjustment',
            self::TRANSFER => 'Transfer',
            self::CONSUMPTION => 'Consumption',
            self::OUTPUT => 'Output',
            self::ASSEMBLY_CONSUMPTION => 'Assembly Consumption',
            self::ASSEMBLY_OUTPUT => 'Assembly Output',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PURCHASE, self::POSITIVE_ADJUSTMENT, self::OUTPUT, self::ASSEMBLY_OUTPUT => 'bg-emerald-100 text-emerald-800',
            self::SALE, self::NEGATIVE_ADJUSTMENT, self::CONSUMPTION, self::ASSEMBLY_CONSUMPTION => 'bg-rose-100 text-rose-800',
            self::TRANSFER => 'bg-blue-100 text-blue-800',
        };
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
            self::ASSEMBLY_CONSUMPTION => 'heroicon-o-puzzle-piece',
            self::ASSEMBLY_OUTPUT => 'heroicon-o-gift',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'icon' => $case->icon(),
            ])
            ->toArray();
    }
}
