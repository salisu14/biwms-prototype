<?php

namespace App\Enums;

enum AccountCategory: string
{
    case CASH = 'CASH';
    case RECEIVABLE = 'RECEIVABLE';
    case INVENTORY = 'INVENTORY';
    case FIXED_ASSET = 'FIXED_ASSET';
    case PAYABLE = 'PAYABLE';
    case ACCRUAL = 'ACCRUAL';
    case REVENUE = 'REVENUE';
    case COGS = 'COGS';
    case OPERATING_EXPENSE = 'OPERATING_EXPENSE';
    case NON_OPERATING = 'NON_OPERATING';

    case CURRENT_ASSET = 'CURRENT_ASSET';
    case FIXED_ASSET_LIABILITIES = 'FIXED_ASSET_LIABILITIES';
    case NON_CURRENT_ASSET = 'NON_CURRENT_ASSET';
    case NON_CURRENT_LIABILITY = 'NON_CURRENT_LIABILITY';

    public function label(): string
    {
        return match($this) {
            self::CASH => 'Cash & Equivalents',
            self::RECEIVABLE => 'Accounts Receivable',
            self::INVENTORY => 'Inventory',
            self::FIXED_ASSET => 'Fixed Assets',
            self::PAYABLE => 'Accounts Payable',
            self::ACCRUAL => 'Accruals',
            self::REVENUE => 'Revenue Category',
            self::COGS => 'COGS Category',
            self::OPERATING_EXPENSE => 'Operating Expense',
            self::NON_OPERATING => 'Non-Operating Items',
            self::CURRENT_ASSET => 'Current Assets',
            self::FIXED_ASSET_LIABILITIES => 'Fixed Asset Liabilities',
            self::NON_CURRENT_ASSET => 'Non-Current Assets',
            self::NON_CURRENT_LIABILITY => 'Non-Current Liabilities',
            default => $this->value,
        };
    }

    public function color(): string
    {
        return match($this) {
            self::CASH, self::RECEIVABLE, self::INVENTORY, self::FIXED_ASSET => 'bg-green-100 text-green-800',
            self::PAYABLE, self::ACCRUAL => 'bg-red-100 text-red-800',
            self::REVENUE => 'bg-blue-100 text-blue-800',
            self::COGS, self::OPERATING_EXPENSE => 'bg-orange-100 text-orange-800',
            self::NON_OPERATING => 'bg-gray-100 text-gray-800',
        };
    }

    public function icon(): string
    {
        return match($this) {
            self::CASH => 'heroicon-o-currency-dollar',
            self::RECEIVABLE => 'heroicon-o-user-plus',
            self::INVENTORY => 'heroicon-o-archive-box',
            self::FIXED_ASSET => 'heroicon-o-home-modern',
            self::PAYABLE => 'heroicon-o-user-minus',
            self::ACCRUAL => 'heroicon-o-clock',
            self::REVENUE => 'heroicon-o-presentation-chart-line',
            self::COGS => 'heroicon-o-truck',
            self::OPERATING_EXPENSE => 'heroicon-o-briefcase',
            self::NON_OPERATING => 'heroicon-o-no-symbol',
            self::CURRENT_ASSET => 'heroicon-o-banknotes',
            self::FIXED_ASSET_LIABILITIES => 'heroicon-o-building-library',
            self::NON_CURRENT_ASSET => 'heroicon-o-cube',
            self::NON_CURRENT_LIABILITY => 'heroicon-o-credit-card',
            default => 'heroicon-o-banknotes',
        };
    }

    public function description(): string
    {
        return match($this) {
            self::CASH => 'Liquid funds and bank balances',
            self::RECEIVABLE => 'Money owed by customers',
            self::INVENTORY => 'Raw materials and finished goods',
            self::FIXED_ASSET => 'Long-term physical properties',
            self::PAYABLE => 'Money owed to suppliers',
            self::ACCRUAL => 'Liabilities for expenses incurred',
            self::REVENUE => 'Income streams from sales',
            self::COGS => 'Direct manufacturing and purchase costs',
            self::OPERATING_EXPENSE => 'Standard day-to-day business costs',
            self::NON_OPERATING => 'Gains/losses outside core business',
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
