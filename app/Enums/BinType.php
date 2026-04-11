<?php

declare(strict_types=1);

namespace App\Enums;

enum BinType: string
{
    case RECEIVING = 'RECEIVING'; // Standardized
    case SHIPPING = 'SHIPPING';     // Standardized
    case PUT_AWAY = 'PUT_AWAY';
    case PICK = 'PICK';
    case STORAGE = 'STORAGE';
    case QC = 'QC';
    case QUALITY_CONTROL = 'QUALITY_CONTROL';
    case BULK = 'BULK'; // Standardized
    case PRODUCTION_SUPPLY = 'PRODUCTION_SUPPLY';
    case PRODUCTION_OUTPUT = 'PRODUCTION_OUTPUT';
    case COOLING = 'COOLING';
    case FREEZING = 'FREEZING';
    case HAZARDOUS = 'HAZARDOUS';

    public function isProductionRelated(): bool
    {
        return in_array($this, [self::PRODUCTION_SUPPLY, self::PRODUCTION_OUTPUT]);
    }

    public function label(): string
    {
        return match ($this) {
            self::RECEIVING => 'Receiving Bin',
            self::SHIPPING => 'Shipping Bin',
            self::PUT_AWAY => 'Put-Away Bin',
            self::PICK => 'Picking Bin',
            self::STORAGE => 'Storage Bin',
            self::BULK => 'Bulk Storage',
            self::QC, self::QUALITY_CONTROL => 'Quality Control Bin',
            self::PRODUCTION_SUPPLY => 'Production Supply',
            self::PRODUCTION_OUTPUT => 'Production Output',
            self::COOLING => 'Cooling Storage',
            self::FREEZING => 'Freezing Storage',
            self::HAZARDOUS => 'Hazardous Material',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RECEIVING => 'bg-emerald-100 text-emerald-800',
            self::SHIPPING => 'bg-amber-100 text-amber-800',
            self::PUT_AWAY => 'bg-blue-100 text-blue-800',
            self::PICK => 'bg-indigo-100 text-indigo-800',
            self::STORAGE => 'bg-slate-100 text-slate-800',
            self::BULK => 'bg-cyan-100 text-cyan-800',
            self::QC, self::QUALITY_CONTROL => 'bg-rose-100 text-rose-800',
            self::PRODUCTION_SUPPLY, self::PRODUCTION_OUTPUT => 'bg-orange-100 text-orange-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RECEIVING => 'heroicon-o-arrow-down-on-square',
            self::SHIPPING => 'heroicon-o-paper-airplane',
            self::PUT_AWAY => 'heroicon-o-inbox-stack',
            self::PICK => 'heroicon-o-hand-raised',
            self::STORAGE => 'heroicon-o-archive-box',
            self::BULK => 'heroicon-o-square-3-stack-3d',
            self::QC, self::QUALITY_CONTROL => 'heroicon-o-magnifying-glass-circle',
            self::PRODUCTION_SUPPLY, self::PRODUCTION_OUTPUT => 'heroicon-o-cog',
            default => 'heroicon-o-cube',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RECEIVING => 'Used for initial unloading and counting.',
            self::SHIPPING => 'Temporary staging for outbound delivery.',
            self::PUT_AWAY => 'Interim bin before moving to main storage.',
            self::PICK => 'Bins optimized for fast order picking.',
            self::STORAGE => 'Standard long-term storage locations.',
            self::BULK => 'Large capacity containers or pallets.',
            self::QC, self::QUALITY_CONTROL => 'Bins held for inspection or quarantine.',
            self::PRODUCTION_SUPPLY => 'Stores components for nearby work centers.',
            self::PRODUCTION_OUTPUT => 'Stores finished goods coming off production.',
            default => 'Specialized storage for specific material needs.',
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
