<?php

namespace App\Enums;

enum SourceDocument: string
{
    case PURCHASE_ORDER = 'PURCHASE_ORDER';
    case TRANSFER_ORDER = 'TRANSFER_ORDER';
    case RETURN_ORDER = 'RETURN_ORDER';
    case SALES_RETURN = 'SALES_RETURN';

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE_ORDER => 'Purchase Order',
            self::TRANSFER_ORDER => 'Transfer Order',
            self::RETURN_ORDER => 'Return Order',
            self::SALES_RETURN => 'Sales Return',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PURCHASE_ORDER => 'bg-blue-100 text-blue-800',
            self::TRANSFER_ORDER => 'bg-indigo-100 text-indigo-800',
            self::RETURN_ORDER => 'bg-orange-100 text-orange-800',
            self::SALES_RETURN => 'bg-rose-100 text-rose-800',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PURCHASE_ORDER => 'heroicon-o-shopping-bag',
            self::TRANSFER_ORDER => 'heroicon-o-arrows-right-left',
            self::RETURN_ORDER => 'heroicon-o-arrow-path',
            self::SALES_RETURN => 'heroicon-o-receipt-refund',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::PURCHASE_ORDER => 'Inventory replenishment from vendors.',
            self::TRANSFER_ORDER => 'Movement between internal locations.',
            self::RETURN_ORDER => 'Items being returned to a supplier.',
            self::SALES_RETURN => 'Items being returned by a customer.',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->map(fn ($case) => $case->label())->toArray();
    }
}
