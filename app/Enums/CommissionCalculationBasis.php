<?php

declare(strict_types=1);

namespace App\Enums;

enum CommissionCalculationBasis: string
{
    case GrossSales = 'gross_sales';
    case NetSales = 'net_sales';
    case LineNetAmount = 'line_net_amount';
    case GrossProfit = 'gross_profit';
    case Quantity = 'quantity';
    case FixedAmount = 'fixed_amount';
}
