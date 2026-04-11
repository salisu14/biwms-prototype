<?php

namespace App\Enums;

enum BinType: string
{
    case RECEIVE = 'RECEIVE';
    case SHIP = 'SHIP';
    case PUT_AWAY = 'PUT_AWAY';
    case PICK = 'PICK';
    case STORAGE = 'STORAGE';
    case QC = 'QC';
    case RECEIVING = 'receiving';
    case SHIPPING = 'shipping';
    case BULK = 'bulk';
    case QUALITY_CONTROL = 'quality_control';
    case PRODUCTION_SUPPLY = 'production_supply';
    case PRODUCTION_OUTPUT = 'production_output';
    case COOLING = 'cooling';
    case FREEZING = 'freezing';
    case HAZARDOUS = 'hazardous';

    public function isProductionRelated(): bool
    {
        return in_array($this, [self::PRODUCTION_SUPPLY, self::PRODUCTION_OUTPUT]);
    }

    public function label(): string
    {
        return match ($this) {
            self::RECEIVE => 'Receiving Bin',
            self::SHIP => 'Shipping Bin',
            self::PUT_AWAY => 'Put-Away Bin',
            self::PICK => 'Picking Bin',
            self::STORAGE => 'Storage Bin',
            self::QC => 'Quality Control Bin',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::RECEIVE => 'bg-emerald-100 text-emerald-800',
            self::SHIP => 'bg-amber-100 text-amber-800',
            self::PUT_AWAY => 'bg-blue-100 text-blue-800',
            self::PICK => 'bg-indigo-100 text-indigo-800',
            self::STORAGE => 'bg-slate-100 text-slate-800',
            self::QC => 'bg-rose-100 text-rose-800',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::RECEIVE => 'heroicon-o-arrow-down-on-square',
            self::SHIP => 'heroicon-o-paper-airplane',
            self::PUT_AWAY => 'heroicon-o-inbox-stack',
            self::PICK => 'heroicon-o-hand-raised',
            self::STORAGE => 'heroicon-o-archive-box',
            self::QC => 'heroicon-o-magnifying-glass-circle',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::RECEIVE => 'Used for initial unloading and counting.',
            self::SHIP => 'Temporary staging for outbound delivery.',
            self::PUT_AWAY => 'Interim bin before moving to main storage.',
            self::PICK => 'Bins optimized for fast order picking.',
            self::STORAGE => 'Standard long-term storage locations.',
            self::QC => 'Bins held for inspection or quarantine.',
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
