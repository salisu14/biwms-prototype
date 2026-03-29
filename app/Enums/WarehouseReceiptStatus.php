<?php

namespace App\Enums;

enum WarehouseReceiptStatus: string
{
    case OPEN = 'OPEN';
    case RELEASED = 'RELEASED';
    case PARTIALLY_RECEIVED = 'PARTIALLY_RECEIVED';
    case RECEIVED = 'RECEIVED';

    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Open',
            self::RELEASED => 'Released',
            self::PARTIALLY_RECEIVED => 'Partially Received',
            self::RECEIVED => 'Received',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::OPEN => 'bg-slate-100 text-slate-800',
            self::RELEASED => 'bg-blue-100 text-blue-800',
            self::PARTIALLY_RECEIVED => 'bg-amber-100 text-amber-800',
            self::RECEIVED => 'bg-emerald-100 text-emerald-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::OPEN => 'heroicon-o-document-plus',
            self::RELEASED => 'heroicon-o-lock-open',
            self::PARTIALLY_RECEIVED => 'heroicon-o-clock',
            self::RECEIVED => 'heroicon-o-check-badge',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::OPEN => 'Initial state; items can be added or modified.',
            self::RELEASED => 'The receipt is finalized and ready for processing.',
            self::PARTIALLY_RECEIVED => 'Some items have been counted and put away.',
            self::RECEIVED => 'All items in the receipt have been successfully processed.',
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
