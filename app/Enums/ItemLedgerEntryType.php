<?php

namespace App\Enums;

enum ItemLedgerEntryType: int
{
    case PURCHASE = 1;
    case SALE = 2;
    case POSITIVE_ADJUSTMENT = 3;
    case NEGATIVE_ADJUSTMENT = 4;
    case TRANSFER = 5;
    case CONSUMPTION = 6;      // Raw Material -> WIP
    case OUTPUT = 7;           // WIP -> Finished Goods
    case CAPACITY = 8;         // Labor/Machine cost to WIP
    case ASSEMBLY_CONSUMPTION = 9;
    case ASSEMBLY_OUTPUT = 10;
    case OVERHEAD = 11;        // Manufacturing overhead to WIP

    public function label(): string
    {
        return match ($this) {
            self::PURCHASE => 'Purchase',
            self::SALE => 'Sale',
            self::POSITIVE_ADJUSTMENT => 'Positive Adjustment',
            self::NEGATIVE_ADJUSTMENT => 'Negative Adjustment',
            self::TRANSFER => 'Transfer',
            self::CONSUMPTION => 'Consumption',
            self::OUTPUT => 'Output',
            self::CAPACITY => 'Capacity',
            self::ASSEMBLY_CONSUMPTION => 'Assembly Consumption',
            self::ASSEMBLY_OUTPUT => 'Assembly Output',
            self::OVERHEAD => 'Overhead',
        };
    }

    public function isProduction(): bool
    {
        return in_array($this, [
            self::CONSUMPTION,
            self::OUTPUT,
            self::CAPACITY,
            self::OVERHEAD,
        ]);
    }
}
