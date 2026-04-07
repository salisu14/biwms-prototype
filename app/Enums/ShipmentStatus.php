<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case OPEN = 'OPEN';
    case RELEASED = 'RELEASED';
    case PARTIALLY_SHIPPED = 'PARTIALLY_SHIPPED';
    case SHIPPED = 'SHIPPED';

    case INVOICED = 'INVOICED';
    case PARTIALLY_INVOICED = 'PARTIALLY_INVOICED';
    case INVOICED_AND_SHIPPED = 'INVOICED_AND_SHIPPED';

    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Open',
            self::RELEASED => 'Released',
            self::PARTIALLY_SHIPPED => 'Partially Shipped',
            self::SHIPPED => 'Shipped',
            self::INVOICED => 'Invoiced',
            self::PARTIALLY_INVOICED => 'Partially Invoiced',
            self::INVOICED_AND_SHIPPED => 'Invoiced and Shipped',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::OPEN => 'bg-gray-100 text-gray-800',
            self::RELEASED => 'bg-blue-100 text-blue-800',
            self::PARTIALLY_SHIPPED => 'bg-amber-100 text-amber-800',
            self::SHIPPED => 'bg-emerald-100 text-emerald-800',
            self::INVOICED => 'bg-purple-100 text-purple-800',
            self::PARTIALLY_INVOICED => 'bg-indigo-100 text-indigo-800',
            self::INVOICED_AND_SHIPPED => 'bg-pink-100 text-pink-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::OPEN => 'heroicon-o-document-text',
            self::RELEASED => 'heroicon-o-lock-open',
            self::PARTIALLY_SHIPPED => 'heroicon-o-clock',
            self::SHIPPED => 'heroicon-o-check-circle',
            self::INVOICED => 'heroicon-o-currency-dollar',
            self::PARTIALLY_INVOICED => 'heroicon-o-arrow-trending-up',
            self::INVOICED_AND_SHIPPED => 'heroicon-o-truck',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
