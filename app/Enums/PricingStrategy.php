<?php

namespace App\Enums;

enum PricingStrategy: string
{
    case STANDARD = 'STANDARD';
    case TIERED = 'TIERED';
    case DYNAMIC = 'DYNAMIC';
    case COST_PLUS = 'COST_PLUS';
    case DISCOUNT_PERCENT = 'DISCOUNT_PERCENT';
    case DISCOUNT_AMOUNT = 'DISCOUNT_AMOUNT';

    public function label(): string
    {
        return match($this) {
            self::STANDARD => 'Standard/Fixed Price',
            self::TIERED => 'Tiered (Quantity Breaks)',
            self::DYNAMIC => 'Dynamic/Formula Based',
            self::COST_PLUS => 'Cost-Plus Markup',
            self::DISCOUNT_PERCENT => 'Percentage Discount',
            self::DISCOUNT_AMOUNT => 'Fixed Amount Discount',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::STANDARD => 'bg-slate-100 text-slate-800',
            self::TIERED => 'bg-indigo-100 text-indigo-800',
            self::DYNAMIC => 'bg-purple-100 text-purple-800',
            self::COST_PLUS => 'bg-amber-100 text-amber-800',
            self::DISCOUNT_PERCENT, self::DISCOUNT_AMOUNT => 'bg-emerald-100 text-emerald-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::STANDARD => 'heroicon-o-currency-dollar',
            self::TIERED => 'heroicon-o-layers',
            self::DYNAMIC => 'heroicon-o-variable',
            self::COST_PLUS => 'heroicon-o-plus-circle',
            self::DISCOUNT_PERCENT => 'heroicon-o-receipt-percent',
            self::DISCOUNT_AMOUNT => 'heroicon-o-tag',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::STANDARD => 'Uses a static, fixed unit price.',
            self::TIERED => 'Price changes based on the volume or quantity ordered.',
            self::DYNAMIC => 'Calculated in real-time based on external variables or formulas.',
            self::COST_PLUS => 'Calculated by adding a specific markup to the item cost.',
            self::DISCOUNT_PERCENT => 'Applies a percentage-based reduction to the list price.',
            self::DISCOUNT_AMOUNT => 'Applies a fixed currency reduction to the list price.',
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
                'description' => $case->description(),
            ])
            ->toArray();
    }
}
