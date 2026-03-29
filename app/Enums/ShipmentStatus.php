<?php

namespace App\Enums;

enum ShipmentStatus: string
{
    case OPEN = 'OPEN';
    case RELEASED = 'RELEASED';
    case PARTIALLY_SHIPPED = 'PARTIALLY_SHIPPED';
    case SHIPPED = 'SHIPPED';

    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Open',
            self::RELEASED => 'Released',
            self::PARTIALLY_SHIPPED => 'Partially Shipped',
            self::SHIPPED => 'Shipped',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::OPEN => 'bg-gray-100 text-gray-800',
            self::RELEASED => 'bg-blue-100 text-blue-800',
            self::PARTIALLY_SHIPPED => 'bg-amber-100 text-amber-800',
            self::SHIPPED => 'bg-emerald-100 text-emerald-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::OPEN => 'heroicon-o-document-text',
            self::RELEASED => 'heroicon-o-lock-open',
            self::PARTIALLY_SHIPPED => 'heroicon-o-clock',
            self::SHIPPED => 'heroicon-o-check-circle',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
            ->toArray();
    }
}
