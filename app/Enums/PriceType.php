<?php

namespace App\Enums;

enum PriceType: string
{
    case UNIT_PRICE = 'UNIT_PRICE';
    case PERCENT_DISCOUNT = 'PERCENT_DISCOUNT';
    case AMOUNT_DISCOUNT = 'AMOUNT_DISCOUNT';
    case COST_PLUS_PERCENT = 'COST_PLUS_PERCENT';
    case COST_PLUS_AMOUNT = 'COST_PLUS_AMOUNT';
    case FORMULA = 'FORMULA';

    public function label(): string
    {
        return match($this) {
            self::UNIT_PRICE => 'Fixed Unit Price',
            self::PERCENT_DISCOUNT => 'Percentage Discount',
            self::AMOUNT_DISCOUNT => 'Amount Discount',
            self::COST_PLUS_PERCENT => 'Cost + % Markup',
            self::COST_PLUS_AMOUNT => 'Cost + Fixed Markup',
            self::FORMULA => 'Complex Formula',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::UNIT_PRICE => 'bg-emerald-100 text-emerald-800',
            self::PERCENT_DISCOUNT, self::AMOUNT_DISCOUNT => 'bg-rose-100 text-rose-800',
            self::COST_PLUS_PERCENT, self::COST_PLUS_AMOUNT => 'bg-amber-100 text-amber-800',
            self::FORMULA => 'bg-purple-100 text-purple-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::UNIT_PRICE => 'heroicon-o-currency-dollar',
            self::PERCENT_DISCOUNT => 'heroicon-o-receipt-percent',
            self::AMOUNT_DISCOUNT => 'heroicon-o-tag',
            self::COST_PLUS_PERCENT => 'heroicon-o-arrow-up-right',
            self::COST_PLUS_AMOUNT => 'heroicon-o-plus-circle',
            self::FORMULA => 'heroicon-o-variable',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::UNIT_PRICE => 'A specific fixed price per unit of measure.',
            self::PERCENT_DISCOUNT => 'A percentage deducted from the base unit price.',
            self::AMOUNT_DISCOUNT => 'A fixed currency amount deducted from the base price.',
            self::COST_PLUS_PERCENT => 'Calculated by adding a percentage to the unit cost.',
            self::COST_PLUS_AMOUNT => 'Calculated by adding a fixed amount to the unit cost.',
            self::FORMULA => 'Price derived from a multi-variable mathematical calculation.',
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
