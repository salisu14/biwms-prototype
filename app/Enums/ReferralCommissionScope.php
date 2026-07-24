<?php

declare(strict_types=1);

namespace App\Enums;

enum ReferralCommissionScope: string
{
    case ALL_ELIGIBLE_SALES = 'ALL_ELIGIBLE_SALES';
    case SPECIFIC_ITEMS = 'SPECIFIC_ITEMS';
    case SPECIFIC_CATEGORIES = 'SPECIFIC_CATEGORIES';
    case SPECIFIC_ITEMS_AND_CATEGORIES = 'SPECIFIC_ITEMS_AND_CATEGORIES';

    public function label(): string
    {
        return match ($this) {
            self::ALL_ELIGIBLE_SALES => 'All Eligible Sales',
            self::SPECIFIC_ITEMS => 'Specific Items',
            self::SPECIFIC_CATEGORIES => 'Specific Categories',
            self::SPECIFIC_ITEMS_AND_CATEGORIES => 'Specific Items and Categories',
        };
    }
}
