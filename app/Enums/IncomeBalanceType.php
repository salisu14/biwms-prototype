<?php

namespace App\Enums;

/**
 * Optional: Enum for 'income_balance' column.
 */
enum IncomeBalanceType: string
{
    case INCOME_STATEMENT = 'Income Statement';
    case BALANCE_SHEET = 'Balance Sheet';
}
