<?php

namespace App\Enums;

enum PriceCalculationMethod: string
{
    case STANDARD = 'STANDARD';
    case COST_PLUS = 'COST_PLUS';
    case PRICE_LIST_ONLY = 'PRICE_LIST_ONLY';

    public function label(): string
    {
        return match($this) {
            self::STANDARD => 'Standard (Unit Price)',
            self::COST_PLUS => 'Cost-Plus Markup',
            self::PRICE_LIST_ONLY => 'Price List Only',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::STANDARD => 'bg-emerald-100 text-emerald-800',
            self::COST_PLUS => 'bg-blue-100 text-blue-800',
            self::PRICE_LIST_ONLY => 'bg-purple-100 text-purple-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::STANDARD => 'heroicon-o-currency-dollar',
            self::COST_PLUS => 'heroicon-o-plus-circle',
            self::PRICE_LIST_ONLY => 'heroicon-o-list-bullet',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::STANDARD => 'Uses the fixed unit price defined on the item card.',
            self::COST_PLUS => 'Dynamically calculates price based on current unit cost plus markup.',
            self::PRICE_LIST_ONLY => 'Strictly enforces pricing defined in active price lists; ignores item card defaults.',
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
