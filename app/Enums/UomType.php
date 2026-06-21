<?php

// app/Enums/UomType.php

namespace App\Enums;

enum UomType: string
{
    case BASE = 'BASE';
    case SALES = 'SALES';
    case PURCHASE = 'PURCHASE';
    case SHIPPING = 'SHIPPING';
    case REPORTING = 'REPORTING';
    case ALTERNATE = 'ALTERNATE';
    case PRODUCTION = 'PRODUCTION';
    case CONSUMPTION = 'CONSUMPTION';

    public function label(): string
    {
        return match ($this) {
            self::BASE => 'Base/Inventory',
            self::SALES => 'Sales',
            self::PURCHASE => 'Purchase',
            self::SHIPPING => 'Shipping',
            self::REPORTING => 'Reporting',
            self::ALTERNATE => 'Alternate',
            self::PRODUCTION => 'Production',
            self::CONSUMPTION => 'Consumption',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::BASE => 'Standard inventory unit of measure',
            self::SALES => 'Unit used for customer sales',
            self::PURCHASE => 'Unit used for vendor purchases',
            self::SHIPPING => 'Unit used for shipping/logistics',
            self::REPORTING => 'Unit used for reports and analytics',
            self::ALTERNATE => 'Alternative unit for special cases',
            self::PRODUCTION => 'Unit used in manufacturing',
            self::CONSUMPTION => 'Unit used for material consumption',
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
}
