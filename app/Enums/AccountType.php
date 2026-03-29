<?php

namespace App\Enums;

enum AccountType: string
{
    case ASSET = 'ASSET';
    case LIABILITY = 'LIABILITY';
    case EQUITY = 'EQUITY';
    case REVENUE = 'REVENUE';
    case COGS = 'COGS';
    case EXPENSE = 'EXPENSE';
    case INTEREST = 'INTEREST';
    case TAX = 'TAX';

    public function label(): string
    {
        return match($this) {
            self::ASSET => 'Asset',
            self::LIABILITY => 'Liability',
            self::EQUITY => 'Equity',
            self::REVENUE => 'Revenue',
            self::COGS => 'Cost of Goods Sold',
            self::EXPENSE => 'Expense',
            self::INTEREST => 'Interest',
            self::TAX => 'Tax',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::ASSET => 'bg-emerald-100 text-emerald-800',
            self::LIABILITY => 'bg-rose-100 text-rose-800',
            self::EQUITY => 'bg-blue-100 text-blue-800',
            self::REVENUE => 'bg-indigo-100 text-indigo-800',
            self::COGS => 'bg-amber-100 text-amber-800',
            self::EXPENSE => 'bg-slate-100 text-slate-800',
            self::INTEREST => 'bg-orange-100 text-orange-800',
            self::TAX => 'bg-red-100 text-red-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::ASSET => 'heroicon-o-banknotes',
            self::LIABILITY => 'heroicon-o-credit-card',
            self::EQUITY => 'heroicon-o-scale',
            self::REVENUE => 'heroicon-o-arrow-trending-up',
            self::COGS => 'heroicon-o-shopping-cart',
            self::EXPENSE => 'heroicon-o-receipt-percent',
            self::INTEREST => 'heroicon-o-receipt-refund',
            self::TAX => 'heroicon-o-building-library',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::ASSET => 'Resources owned (10000-19999)',
            self::LIABILITY => 'Obligations owed to others (20000-29999)',
            self::EQUITY => 'Owner\'s residual interest (30000-39999)',
            self::REVENUE => 'Income from primary activities (40000-49999)',
            self::COGS => 'Direct costs of production (50000-59999)',
            self::EXPENSE => 'Operating and overhead costs (60000-69999)',
            self::INTEREST => 'Financial costs and earnings (70000-79999)',
            self::TAX => 'Governmental tax obligations (80000-89999)',
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
