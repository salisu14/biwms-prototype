<?php

declare(strict_types=1);

namespace App\Enums;

enum DepartmentType: string
{
    case OPERATING = 'operating';
    case ADMINISTRATIVE = 'administrative';
    case SALES = 'sales';
    case PURCHASING = 'purchasing';
    case PRODUCTION = 'production';
    case WAREHOUSE = 'warehouse';
    case FINANCE = 'finance';
    case HR = 'human_resources';
    case IT = 'information_technology';

    public function label(): string
    {
        return match ($this) {
            self::OPERATING => 'Operating',
            self::ADMINISTRATIVE => 'Administrative',
            self::SALES => 'Sales & Marketing',
            self::PURCHASING => 'Purchasing',
            self::PRODUCTION => 'Production/Manufacturing',
            self::WAREHOUSE => 'Warehouse/Logistics',
            self::FINANCE => 'Finance/Accounting',
            self::HR => 'Human Resources',
            self::IT => 'Information Technology',
        };
    }

    public function defaultExpenseAccount(): ?string
    {
        return match ($this) {
            self::SALES => '6110',
            self::PURCHASING => '6120',
            self::PRODUCTION => '6200',
            self::WAREHOUSE => '6130',
            self::FINANCE => '6140',
            self::HR => '6150',
            self::IT => '6160',
            self::ADMINISTRATIVE => '6100',
            default => null,
        };
    }
}
