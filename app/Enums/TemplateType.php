<?php

namespace App\Enums;

enum TemplateType: string
{
    case ITEM = 'ITEM';
    case TRANSFER = 'TRANSFER';
    case PHYSICAL_INVENTORY = 'PHYSICAL_INVENTORY';
    case RECLASSIFICATION = 'RECLASSIFICATION';
    case CONSUMPTION = 'CONSUMPTION';
    case OUTPUT = 'OUTPUT';

    public function label(): string
    {
        return match ($this) {
            self::ITEM => 'Item Journal',
            self::TRANSFER => 'Transfer Journal',
            self::PHYSICAL_INVENTORY => 'Physical Inventory Journal',
            self::RECLASSIFICATION => 'Reclassification Journal',
            self::CONSUMPTION => 'Consumption Journal',
            self::OUTPUT => 'Output Journal',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::ITEM => 'bg-blue-100 text-blue-800',
            self::TRANSFER => 'bg-indigo-100 text-indigo-800',
            self::PHYSICAL_INVENTORY => 'bg-emerald-100 text-emerald-800',
            self::RECLASSIFICATION => 'bg-amber-100 text-amber-800',
            self::CONSUMPTION => 'bg-orange-100 text-orange-800',
            self::OUTPUT => 'bg-purple-100 text-purple-800',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::ITEM => 'heroicon-o-cube',
            self::TRANSFER => 'heroicon-o-arrows-right-left',
            self::PHYSICAL_INVENTORY => 'heroicon-o-clipboard-document-list',
            self::RECLASSIFICATION => 'heroicon-o-tag',
            self::CONSUMPTION => 'heroicon-o-fire',
            self::OUTPUT => 'heroicon-o-beaker',
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
