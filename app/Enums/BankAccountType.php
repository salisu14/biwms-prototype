<?php

namespace App\Enums;

enum BankAccountType: string
{
    case CHECKING = 'CHECKING';
    case SAVINGS = 'SAVINGS';
    case MONEY_MARKET = 'MONEY_MARKET';
    case CERTIFICATE_OF_DEPOSIT = 'CERTIFICATE_OF_DEPOSIT';
    case FOREIGN_CURRENCY = 'FOREIGN_CURRENCY';

    public function label(): string
    {
        return match($this) {
            self::CHECKING => 'Checking',
            self::SAVINGS => 'Savings',
            self::MONEY_MARKET => 'Money Market',
            self::CERTIFICATE_OF_DEPOSIT => 'Certificate of Deposit',
            self::FOREIGN_CURRENCY => 'Foreign Currency',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::CHECKING => 'bg-emerald-100 text-emerald-800',
            self::SAVINGS => 'bg-rose-100 text-rose-800',
            self::MONEY_MARKET => 'bg-blue-100 text-blue-800',
            self::CERTIFICATE_OF_DEPOSIT => 'bg-indigo-100 text-indigo-800',
            self::FOREIGN_CURRENCY => 'bg-amber-100 text-amber-800'
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::CHECKING => 'heroicon-o-banknotes',
            self::SAVINGS => 'heroicon-o-credit-card',
            self::MONEY_MARKET => 'heroicon-o-scale',
            self::CERTIFICATE_OF_DEPOSIT => 'heroicon-o-arrow-trending-up',
            self::FOREIGN_CURRENCY => 'heroicon-o-shopping-cart'
        };
    }

    public function description(): string
    {
        return match($this) {
            self::CHECKING => 'Operating, payroll, tax, petty cash',
            self::SAVINGS => 'General savings, emergency reserve',
            self::MONEY_MARKET => 'Investment account',
            self::CERTIFICATE_OF_DEPOSIT => '6-month and 12-month CDs',
            self::FOREIGN_CURRENCY => 'USD, EUR, GBP, CAD operations'
        };
    }

    public static function options(): array
    {
        return collect(self::cases())
            ->map(fn ($case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'icon' => $case->icon(),
                'description' => $case->description(),
            ])
            ->toArray();
    }
}
