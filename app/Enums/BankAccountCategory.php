<?php

namespace App\Enums;

enum BankAccountCategory: string
{
    case OPERATING = 'operating';
    case SAVINGS = 'savings';
    case INVESTMENT = 'investment';
    case TAX_RESERVE = 'tax_reserve';
    case PAYROLL = 'payroll';
    case PETTY_CASH = 'petty_cash';

    public function label(): string
    {
        return match ($this) {
            self::OPERATING => 'Operating Account',
            self::SAVINGS => 'Savings Account',
            self::INVESTMENT => 'Investment Account',
            self::TAX_RESERVE => 'Tax Reserve',
            self::PAYROLL => 'Payroll Account',
            self::PETTY_CASH => 'Petty Cash',
        };
    }
}
