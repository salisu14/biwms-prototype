<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemType: string
{
    case INVENTORY = 'INVENTORY';
    case RAW_MATERIAL = 'RAW_MATERIAL';
    case SEMI_FINISHED = 'SEMI_FINISHED';
    case FINISHED_GOOD = 'FINISHED_GOOD';
    case PACKAGING = 'PACKAGING';
    case CONSUMABLE = 'CONSUMABLE';
    case SPARE_PART = 'SPARE_PART';
    case SERVICE = 'SERVICE';

    public function label(): string
    {
        return match ($this) {
            self::INVENTORY => 'Inventory Item',
            self::RAW_MATERIAL => 'Raw Material',
            self::SEMI_FINISHED => 'Semi-Finished Good',
            self::FINISHED_GOOD => 'Finished Good',
            self::PACKAGING => 'Packaging Material',
            self::CONSUMABLE => 'Consumable',
            self::SPARE_PART => 'Spare Part',
            self::SERVICE => 'Service',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::INVENTORY => 'General inventory item not assigned to a more specific operational type',
            self::RAW_MATERIAL => 'Material consumed during production or manufacturing',
            self::SEMI_FINISHED => 'Manufactured intermediate item awaiting further processing or packaging',
            self::FINISHED_GOOD => 'Completed product ready for sale or distribution',
            self::PACKAGING => 'Material used to contain, protect, identify or transport products',
            self::CONSUMABLE => 'Operational supply consumed without becoming part of the finished product',
            self::SPARE_PART => 'Replacement part used for equipment maintenance',
            self::SERVICE => 'Non-physical service item',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::INVENTORY => 'heroicon-m-cube',
            self::RAW_MATERIAL => 'heroicon-m-beaker',
            self::SEMI_FINISHED => 'heroicon-m-arrow-path-rounded-square',
            self::FINISHED_GOOD => 'heroicon-m-check-badge',
            self::PACKAGING => 'heroicon-m-archive-box',
            self::CONSUMABLE => 'heroicon-m-squares-plus',
            self::SPARE_PART => 'heroicon-m-wrench',
            self::SERVICE => 'heroicon-m-wrench-screwdriver',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INVENTORY => 'gray',
            self::RAW_MATERIAL => 'warning',
            self::SEMI_FINISHED => 'info',
            self::FINISHED_GOOD => 'success',
            self::PACKAGING => 'primary',
            self::CONSUMABLE => 'gray',
            self::SPARE_PART => 'warning',
            self::SERVICE => 'secondary',
        };
    }

    public function requiresInventoryTracking(): bool
    {
        return $this !== self::SERVICE;
    }

    public function requiresBatchTracking(): bool
    {
        return in_array(
            $this,
            [
                self::RAW_MATERIAL,
                self::SEMI_FINISHED,
                self::FINISHED_GOOD,
                self::PACKAGING,
            ],
            true,
        );
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $case): string => $case->value,
            self::cases(),
        );
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return array<int, string>
     */
    public static function inventoryTypes(): array
    {
        return [
            self::INVENTORY->value,
            self::RAW_MATERIAL->value,
            self::SEMI_FINISHED->value,
            self::FINISHED_GOOD->value,
            self::PACKAGING->value,
            self::CONSUMABLE->value,
            self::SPARE_PART->value,
        ];
    }
}
