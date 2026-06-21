<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Enum for Sales Order Types
 */
enum SalesOrderType: string implements HasLabel
{
    case SalesOrder = 'SALES_ORDER';
    case ReturnOrder = 'RETURN_ORDER';
    case Replacement = 'REPLACEMENT';
    case Contract = 'CONTRACT';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::SalesOrder => 'Sales Order',
            self::ReturnOrder => 'Return Order',
            self::Replacement => 'Replacement',
            self::Contract => 'Contract',
        };
    }
}
