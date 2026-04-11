<?php

declare(strict_types=1);

namespace App\Enums;

enum TangibleAssetType: string
{
    case PLANT_MACHINERY = 'plant_machinery';
    case BUILDING = 'building';
    case VEHICLE = 'vehicle';
    case FURNITURE_FIXTURES = 'furniture_fixtures';
    case LAND = 'land';
    case OFFICE_EQUIPMENT = 'office_equipment';

    public function label(): string
    {
        return match ($this) {
            self::PLANT_MACHINERY => 'Plant & Machinery',
            self::BUILDING => 'Building',
            self::VEHICLE => 'Vehicle',
            self::FURNITURE_FIXTURES => 'Furniture & Fixtures',
            self::LAND => 'Land',
            self::OFFICE_EQUIPMENT => 'Office Equipment',
        };
    }

    public function defaultUsefulLifeYears(): int
    {
        return match ($this) {
            self::PLANT_MACHINERY => 10,
            self::BUILDING => 25,
            self::VEHICLE => 5,
            self::FURNITURE_FIXTURES => 7,
            self::LAND => 0, // Non-depreciable
            self::OFFICE_EQUIPMENT => 5,
        };
    }

    public function isDepreciable(): bool
    {
        return $this !== self::LAND;
    }

    public function requiresMaintenance(): bool
    {
        return in_array($this, [self::PLANT_MACHINERY, self::VEHICLE, self::BUILDING], true);
    }
}
