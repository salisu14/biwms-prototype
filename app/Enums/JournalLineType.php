<?php

declare(strict_types=1);

namespace App\Enums;

enum JournalLineType: string
{
    // Item Journal Types
    case POSITIVE_ADJUSTMENT = 'positive_adjustment';
    case NEGATIVE_ADJUSTMENT = 'negative_adjustment';
    case PURCHASE = 'purchase';
    case SALE = 'sale';
    case TRANSFER = 'transfer';
    case RECLASSIFICATION = 'reclassification';
    case CONSUMPTION = 'consumption';
    case OUTPUT = 'output';

    // Production Journal Types
    case PROD_CONSUMPTION = 'prod_consumption';
    case PROD_OUTPUT = 'prod_output';
    case CAPACITY = 'capacity';
    case PROD_SCRAP = 'prod_scrap';

    // Warehouse Journal Types
    case WH_PICK = 'wh_pick';
    case WH_PUT_AWAY = 'wh_put_away';
    case WH_MOVEMENT = 'wh_movement';
    case WH_ADJUSTMENT = 'wh_adjustment';
    case WH_PHYSICAL_INVENTORY = 'wh_physical_inventory';

    // General Journal Types
    case GL_ENTRY = 'gl_entry';
    case GL_ALLOCATION = 'gl_allocation';

    public function isInventoryImpact(): bool
    {
        return in_array($this, [
            self::POSITIVE_ADJUSTMENT,
            self::NEGATIVE_ADJUSTMENT,
            self::PURCHASE,
            self::SALE,
            self::TRANSFER,
            self::CONSUMPTION,
            self::OUTPUT,
            self::PROD_CONSUMPTION,
            self::PROD_OUTPUT,
        ]);
    }

    public function isWarehouseImpact(): bool
    {
        return in_array($this, [
            self::WH_PICK,
            self::WH_PUT_AWAY,
            self::WH_MOVEMENT,
            self::WH_ADJUSTMENT,
            self::WH_PHYSICAL_INVENTORY,
            self::TRANSFER,
        ]);
    }
}
