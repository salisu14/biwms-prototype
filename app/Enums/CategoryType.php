<?php

declare(strict_types=1);

namespace App\Enums;

enum CategoryType: string
{
    case FINISHED_GOOD = 'FINISHED_GOOD';
    case SEMI_FINISHED = 'SEMI_FINISHED';
    case RAW_MATERIAL = 'RAW_MATERIAL';
    case PACKAGING = 'PACKAGING';
    case CONSUMABLE = 'CONSUMABLE';
    case SPARE_PART = 'SPARE_PART';

    public function label(): string
    {
        return match ($this) {
            self::FINISHED_GOOD => 'Finished Goods',
            self::SEMI_FINISHED => 'Semi-Finished Goods',
            self::RAW_MATERIAL => 'Raw Materials',
            self::PACKAGING => 'Packaging Materials',
            self::CONSUMABLE => 'Consumables and Supplies',
            self::SPARE_PART => 'Maintenance and Spare Parts',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::FINISHED_GOOD => 'success',
            self::SEMI_FINISHED => 'info',
            self::RAW_MATERIAL => 'warning',
            self::PACKAGING => 'primary',
            self::CONSUMABLE => 'gray',
            self::SPARE_PART => 'warning',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::FINISHED_GOOD => 'heroicon-o-check-badge',
            self::SEMI_FINISHED => 'heroicon-o-arrow-path-rounded-square',
            self::RAW_MATERIAL => 'heroicon-o-beaker',
            self::PACKAGING => 'heroicon-o-archive-box',
            self::CONSUMABLE => 'heroicon-o-squares-plus',
            self::SPARE_PART => 'heroicon-o-wrench',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::FINISHED_GOOD => 'Completed products ready for sale, storage or distribution',
            self::SEMI_FINISHED => 'Manufactured intermediate products awaiting further processing',
            self::RAW_MATERIAL => 'Materials and ingredients consumed during manufacturing',
            self::PACKAGING => 'Primary, secondary and tertiary packaging materials',
            self::CONSUMABLE => 'Operational supplies consumed during business activities',
            self::SPARE_PART => 'Maintenance, repair and equipment replacement materials',
        };
    }

    public function allowsMultiple(): bool
    {
        return false;
    }

    /**
     * @return array<string, string>
     */
    public static function selectOptions(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function options(): array
    {
        return array_map(
            static fn (self $case): array => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'icon' => $case->icon(),
                'description' => $case->description(),
                'allows_multiple' => $case->allowsMultiple(),
            ],
            self::cases(),
        );
    }
}
