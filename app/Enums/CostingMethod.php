<?php

namespace App\Enums;

enum CostingMethod: string
{
    case FIFO = 'FIFO';
    case LIFO = 'LIFO';
    case AVERAGE = 'AVERAGE';
    case STANDARD = 'STANDARD';
    case SPECIFIC = 'SPECIFIC';

    public function label(): string
    {
        return match($this) {
            self::FIFO => 'First-In, First-Out (FIFO)',
            self::LIFO => 'Last-In, First-Out (LIFO)',
            self::AVERAGE => 'Average Cost',
            self::STANDARD => 'Standard Cost',
            self::SPECIFIC => 'Specific Identification',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::FIFO => 'bg-emerald-100 text-emerald-800',
            self::LIFO => 'bg-amber-100 text-amber-800',
            self::AVERAGE => 'bg-blue-100 text-blue-800',
            self::STANDARD => 'bg-purple-100 text-purple-800',
            self::SPECIFIC => 'bg-indigo-100 text-indigo-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::FIFO => 'heroicon-o-arrow-right-circle',
            self::LIFO => 'heroicon-o-arrow-left-circle',
            self::AVERAGE => 'heroicon-o-divide',
            self::STANDARD => 'heroicon-o-check-badge',
            self::SPECIFIC => 'heroicon-o-fingerprint',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::FIFO => 'Assumes the oldest inventory items are sold first.',
            self::LIFO => 'Assumes the newest inventory items are sold first.',
            self::AVERAGE => 'Costs are calculated based on the weighted average of all units.',
            self::STANDARD => 'Uses a fixed pre-determined cost regardless of actual purchase price.',
            self::SPECIFIC => 'Tracks the actual cost of each individual specific unit.',
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
