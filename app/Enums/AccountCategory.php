<?php

namespace App\Enums;

enum AccountCategory: string
{
    // Balance Sheet
    case ASSET = 'asset';
    case LIQUID_ASSET = 'liquid_asset';
    case RECEIVABLE = 'receivable';
    case INVENTORY = 'inventory';
    case FIXED_ASSET = 'fixed_asset';
    case LIABILITY = 'liability';
    case PAYABLE = 'payable';
    case EQUITY = 'equity';

    // Income Statement
    case REVENUE = 'revenue';
    case COGS = 'cogs';
    case DIRECT_EXPENSE = 'direct_expense';
    case INDIRECT_EXPENSE = 'indirect_expense';
    case OPERATING_EXPENSE = 'operating_expense';
    case OTHER_INCOME_EXPENSE = 'other_income_expense';

    public function isIncomeStatement(): bool
    {
        return in_array($this, [
            self::REVENUE,
            self::COGS,
            self::DIRECT_EXPENSE,
            self::INDIRECT_EXPENSE,
            self::OPERATING_EXPENSE,
            self::OTHER_INCOME_EXPENSE
        ]);
    }

    public function isBalanceSheet(): bool
    {
        return !$this->isIncomeStatement();
    }
}
