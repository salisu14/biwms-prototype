<?php

namespace App\Enums;

enum ProductionOrderSourceType: string
{
    case ITEM = 'ITEM';
    case FAMILY = 'FAMILY';
    case SALES_HEADER = 'SALES_HEADER';

    public function label(): string
    {
        return match($this) {
            self::ITEM => 'Item',
            self::FAMILY => 'Item Family',
            self::SALES_HEADER => 'Sales Order',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::ITEM => 'Production based on single item',
            self::FAMILY => 'Production based on item family',
            self::SALES_HEADER => 'Production linked to sales order',
        };
    }
}
