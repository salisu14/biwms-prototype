<?php

namespace App\Enums;

/**
 * Corresponds to the 'account_category' column in gl_accounts.
 */
enum GlAccountCategory: string
{
    case ASSETS = 'Assets';
    case LIABILITIES = 'Liabilities';
    case EQUITY = 'Equity';
    case INCOME = 'Income';
    case COGS = 'Cost of Goods Sold';
    case EXPENSE = 'Expense';
    case CAPEX = 'Capex';

    public function label(): string
    {
        return $this->value;
    }
}
