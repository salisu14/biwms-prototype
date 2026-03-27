<?php
// app/Enums/ItemType.php

namespace App\Enums;

enum ItemType: string
{
    case RAW_MATERIAL = 'RAW_MATERIAL';
    case FINISHED_GOOD = 'FINISHED_GOOD';
    case PACKAGING = 'PACKAGING';
    case SPARE_PART = 'SPARE_PART';
    case SERVICE = 'SERVICE';

    public function label(): string
    {
        return match($this) {
            self::RAW_MATERIAL => 'Raw Material',
            self::FINISHED_GOOD => 'Finished Good',
            self::PACKAGING => 'Packaging Material',
            self::SPARE_PART => 'Spare Part',
            self::SERVICE => 'Service',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::RAW_MATERIAL => 'Materials used in production or manufacturing',
            self::FINISHED_GOOD => 'Completed products ready for sale',
            self::PACKAGING => 'Materials used for packaging finished goods',
            self::SPARE_PART => 'Replacement parts for equipment maintenance',
            self::SERVICE => 'Non-physical service items',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::RAW_MATERIAL => 'heroicon-m-beaker',
            self::FINISHED_GOOD => 'heroicon-m-check-badge',
            self::PACKAGING => 'heroicon-m-box',
            self::SPARE_PART => 'heroicon-m-wrench',
            self::SERVICE => 'heroicon-m-wrench-screwdriver',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::RAW_MATERIAL => 'warning',
            self::FINISHED_GOOD => 'success',
            self::PACKAGING => 'info',
            self::SPARE_PART => 'secondary',
            self::SERVICE => 'primary',
        };
    }

    public function requiresInventoryTracking(): bool
    {
        return $this !== self::SERVICE;
    }

    public function requiresBatchTracking(): bool
    {
        return in_array($this, [self::RAW_MATERIAL, self::FINISHED_GOOD, self::PACKAGING]);
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

    public static function inventoryTypes(): array
    {
        return [
            self::RAW_MATERIAL->value,
            self::FINISHED_GOOD->value,
            self::PACKAGING->value,
            self::SPARE_PART->value,
        ];
    }
}
