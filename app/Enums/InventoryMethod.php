<?php
// app/Enums/InventoryMethod.php

namespace App\Enums;

enum InventoryMethod: string
{
    case FIFO = 'FIFO';
    case LIFO = 'LIFO';
    case AVERAGE = 'AVERAGE';
    case STANDARD = 'STANDARD';

    public function label(): string
    {
        return match($this) {
            self::FIFO => 'FIFO (First In, First Out)',
            self::LIFO => 'LIFO (Last In, First Out)',
            self::AVERAGE => 'Weighted Average',
            self::STANDARD => 'Standard Cost',
        };
    }

    public function shortLabel(): string
    {
        return match($this) {
            self::FIFO => 'FIFO',
            self::LIFO => 'LIFO',
            self::AVERAGE => 'Average',
            self::STANDARD => 'Standard',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::FIFO => 'Assumes oldest inventory is used first. Good for perishables.',
            self::LIFO => 'Assumes newest inventory is used first. Tax advantages in inflation.',
            self::AVERAGE => 'Calculates weighted average cost. Smoothes price fluctuations.',
            self::STANDARD => 'Uses predetermined standard cost. Requires variance analysis.',
        };
    }

    public function requiresCostLayerTracking(): bool
    {
        return in_array($this, [self::FIFO, self::LIFO]);
    }

    public function requiresStandardCostMaintenance(): bool
    {
        return $this === self::STANDARD;
    }

    public function getCalculationFormula(): string
    {
        return match($this) {
            self::FIFO => 'Oldest unit cost applied to issues',
            self::LIFO => 'Newest unit cost applied to issues',
            self::AVERAGE => 'Total Cost / Total Quantity = Unit Cost',
            self::STANDARD => 'Predefined standard cost (variances tracked separately)',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function options(): array
    {
        return array_reduce(self::cases(), function ($carry, $case) {
            $carry[$case->value] = $case->label();
            return $carry;
        }, []);
    }

    public static function optionsWithDescription(): array
    {
        return array_reduce(self::cases(), function ($carry, $case) {
            $carry[$case->value] = [
                'label' => $case->label(),
                'description' => $case->description(),
            ];
            return $carry;
        }, []);
    }

    public static function perpetualMethods(): array
    {
        return [self::FIFO, self::LIFO, self::AVERAGE];
    }

    public static function periodicMethods(): array
    {
        return [self::AVERAGE, self::STANDARD];
    }
}
