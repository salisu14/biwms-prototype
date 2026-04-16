<?php

namespace App\Enums;

/**
 * Defines whether an account belongs to the Balance Sheet or Income Statement.
 * Backed by integers to match the tinyInteger database column.
 */
enum IncomeBalanceType: int
{
    case BALANCE_SHEET = 0;
    case INCOME_STATEMENT = 1;

    public function label(): string
    {
        return match ($this) {
            self::BALANCE_SHEET => 'Balance Sheet',
            self::INCOME_STATEMENT => 'Income Statement',
        };
    }

    public function isIncomeStatement(): bool
    {
        return $this === self::INCOME_STATEMENT;
    }

    public function isBalanceSheet(): bool
    {
        return $this === self::BALANCE_SHEET;
    }
}
