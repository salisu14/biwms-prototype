<?php

namespace App\Enums;

enum RevenueCategory: string
{
    case SALES_PRODUCT = 'sales_product';
    case SALES_SERVICE = 'sales_service';
    case SALES_RETURN = 'sales_return'; // Contra-revenue
    case SALES_DISCOUNT = 'sales_discount'; // Contra-revenue
    case INTEREST_INCOME = 'interest_income';
    case GAIN_ON_DISPOSAL = 'gain_on_disposal';

    public function label(): string
    {
        return match ($this) {
            self::SALES_PRODUCT => 'Sales - Products',
            self::SALES_SERVICE => 'Sales - Services',
            self::SALES_RETURN => 'Sales Returns',
            self::SALES_DISCOUNT => 'Sales Discounts',
            self::INTEREST_INCOME => 'Interest Income',
            self::GAIN_ON_DISPOSAL => 'Gain on Disposal of Assets',
        };
    }

    public function isContraRevenue(): bool
    {
        return in_array($this, [self::SALES_RETURN, self::SALES_DISCOUNT], true);
    }
}
