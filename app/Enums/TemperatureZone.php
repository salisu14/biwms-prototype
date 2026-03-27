<?php
// app/Enums/TemperatureZone.php

namespace App\Enums;

enum TemperatureZone: string
{
    case AMBIENT = 'AMBIENT';
    case COOL = 'COOL';
    case COLD = 'COLD';
    case FROZEN = 'FROZEN';

    public function label(): string
    {
        return match($this) {
            self::AMBIENT => 'Ambient (15-25°C)',
            self::COOL => 'Cool (8-15°C)',
            self::COLD => 'Cold (2-8°C)',
            self::FROZEN => 'Frozen (-18 to -25°C)',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::AMBIENT => 'bg-gray-100 text-gray-800 border-gray-300',
            self::COOL => 'bg-teal-100 text-teal-800 border-teal-300',
            self::COLD => 'bg-blue-100 text-blue-800 border-blue-300',
            self::FROZEN => 'bg-indigo-100 text-indigo-800 border-indigo-300',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::AMBIENT => 'thermometer-half',
            self::COOL => 'thermometer-quarter',
            self::COLD => 'snowflake',
            self::FROZEN => 'icicles',
        };
    }

    /**
     * Temperature range in Celsius
     */
    public function temperatureRange(): array
    {
        return match($this) {
            self::AMBIENT => ['min' => 15, 'max' => 25],
            self::COOL => ['min' => 8, 'max' => 15],
            self::COLD => ['min' => 2, 'max' => 8],
            self::FROZEN => ['min' => -25, 'max' => -18],
        };
    }

    /**
     * Whether continuous monitoring is required
     */
    public function requiresContinuousMonitoring(): bool
    {
        return in_array($this, [self::COLD, self::FROZEN]);
    }

    /**
     * Excursion tolerance in hours before product compromise
     */
    public function excursionTolerance(): int
    {
        return match($this) {
            self::AMBIENT => 24,
            self::COOL => 4,
            self::COLD => 2,
            self::FROZEN => 1,
        };
    }

    /**
     * ICH Q1A stability zone
     */
    public function ichZone(): string
    {
        return match($this) {
            self::AMBIENT => 'Zone IVb (30°C/75% RH)',
            self::COOL => 'Zone III (30°C/65% RH)',
            self::COLD, self::FROZEN => 'Refrigerated/Frozen',
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
                'temp_range' => $case->temperatureRange(),
                'requires_monitoring' => $case->requiresContinuousMonitoring(),
            ])
            ->toArray();
    }
}
