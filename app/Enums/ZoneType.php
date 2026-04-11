<?php

namespace App\Enums;

enum ZoneType: string
{
    case RECEIVING = 'RECEIVING';
    case STORAGE = 'STORAGE';
    case PICKING = 'PICKING';
    case SHIPPING = 'SHIPPING';
    case QUALITY_CONTROL = 'QUALITY_CONTROL';
    case CROSS_DOCK = 'CROSS_DOCK';

    public function label(): string
    {
        return match ($this) {
            self::RECEIVING => 'Receiving Zone',
            self::STORAGE => 'Storage/Putaway',
            self::PICKING => 'Picking Area',
            self::SHIPPING => 'Shipping/Dispatch',
            self::QUALITY_CONTROL => 'Quality Control (QC)',
            self::CROSS_DOCK => 'Cross-Docking Area',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RECEIVING => 'bg-emerald-100 text-emerald-800',
            self::STORAGE => 'bg-blue-100 text-blue-800',
            self::PICKING => 'bg-indigo-100 text-indigo-800',
            self::SHIPPING => 'bg-amber-100 text-amber-800',
            self::QUALITY_CONTROL => 'bg-rose-100 text-rose-800',
            self::CROSS_DOCK => 'bg-purple-100 text-purple-800',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RECEIVING => 'heroicon-o-arrow-down-tray',
            self::STORAGE => 'heroicon-o-square-3-stack-3d',
            self::PICKING => 'heroicon-o-hand-raised',
            self::SHIPPING => 'heroicon-o-truck',
            self::QUALITY_CONTROL => 'heroicon-o-clipboard-document-check',
            self::CROSS_DOCK => 'heroicon-o-arrows-right-left',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RECEIVING => 'Inbound area for offloading and initial tally.',
            self::STORAGE => 'Main warehouse areas for long or short term stock.',
            self::PICKING => 'Optimized area for order fulfillment and staging.',
            self::SHIPPING => 'Outbound area for packing, labeling, and loading.',
            self::QUALITY_CONTROL => 'Designated area for inspections and testing.',
            self::CROSS_DOCK => 'Direct transfer area from receiving to shipping.',
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
