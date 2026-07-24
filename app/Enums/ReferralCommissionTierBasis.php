<?php

declare(strict_types=1);

namespace App\Enums;

enum ReferralCommissionTierBasis: string
{
    case SALES_AMOUNT = 'SALES_AMOUNT';
    case QUANTITY = 'QUANTITY';
    case TRANSACTION_COUNT = 'TRANSACTION_COUNT';

    public function label(): string
    {
        return match ($this) {
            self::SALES_AMOUNT => 'Sales Amount',
            self::QUANTITY => 'Quantity',
            self::TRANSACTION_COUNT => 'Transaction Count',
        };
    }
}
