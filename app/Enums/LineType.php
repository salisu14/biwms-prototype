<?php

namespace App\Enums;

enum LineType: string
{
    case SALES = 'SALES';
    case SALES_CREDIT_MEMO = 'SALES_CREDIT_MEMO';
    case SALES_PREPAYMENT = 'SALES_PREPAYMENT';
    case PURCHASE = 'PURCHASE';
    case PURCHASE_CREDIT_MEMO = 'PURCHASE_CREDIT_MEMO';
    case PURCHASE_PREPAYMENT = 'PURCHASE_PREPAYMENT';
    case COGS = 'COGS';
    case INVENTORY_ADJUSTMENT = 'INVENTORY_ADJUSTMENT';
    case DIRECT_COST_APPLIED = 'DIRECT_COST_APPLIED';
    case OVERHEAD_APPLIED = 'OVERHEAD_APPLIED';
    case PURCHASE_VARIANCE = 'PURCHASE_VARIANCE';
    case PRODUCTION_VARIANCE = 'PRODUCTION_VARIANCE';

    public function label(): string
    {
        return match ($this) {
            self::SALES => 'Sales Revenue',
            self::SALES_CREDIT_MEMO => 'Sales Credit Memo',
            self::SALES_PREPAYMENT => 'Sales Prepayment',
            self::PURCHASE => 'Purchase Expense',
            self::PURCHASE_CREDIT_MEMO => 'Purchase Credit Memo',
            self::PURCHASE_PREPAYMENT => 'Purchase Prepayment',
            self::COGS => 'Cost of Goods Sold',
            self::INVENTORY_ADJUSTMENT => 'Inventory Adjustment',
            self::DIRECT_COST_APPLIED => 'Direct Cost Applied',
            self::OVERHEAD_APPLIED => 'Overhead Applied',
            self::PURCHASE_VARIANCE => 'Purchase Variance',
            self::PRODUCTION_VARIANCE => 'Production Variance',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::SALES, self::PURCHASE_PREPAYMENT => 'bg-emerald-100 text-emerald-800',
            self::PURCHASE, self::SALES_PREPAYMENT => 'bg-amber-100 text-amber-800',
            self::SALES_CREDIT_MEMO, self::PURCHASE_CREDIT_MEMO => 'bg-rose-100 text-rose-800',
            self::COGS, self::DIRECT_COST_APPLIED, self::OVERHEAD_APPLIED => 'bg-blue-100 text-blue-800',
            self::INVENTORY_ADJUSTMENT, self::PURCHASE_VARIANCE, self::PRODUCTION_VARIANCE => 'bg-slate-100 text-slate-800',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::SALES => 'heroicon-o-shopping-bag',
            self::SALES_CREDIT_MEMO => 'heroicon-o-arrow-uturn-left',
            self::SALES_PREPAYMENT => 'heroicon-o-clock',
            self::PURCHASE => 'heroicon-o-shopping-cart',
            self::PURCHASE_CREDIT_MEMO => 'heroicon-o-receipt-refund',
            self::PURCHASE_PREPAYMENT => 'heroicon-o-document-check',
            self::COGS => 'heroicon-o-calculator',
            self::INVENTORY_ADJUSTMENT => 'heroicon-o-adjustments-horizontal',
            self::DIRECT_COST_APPLIED => 'heroicon-o-plus-circle',
            self::OVERHEAD_APPLIED => 'heroicon-o-cog-8-tooth',
            self::PURCHASE_VARIANCE => 'heroicon-o-exclamation-triangle',
            self::PRODUCTION_VARIANCE => 'heroicon-o-variable',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::SALES => 'Revenue generated from sales',
            self::SALES_CREDIT_MEMO => 'Reductions in revenue for returns/allowances',
            self::SALES_PREPAYMENT => 'Customer payments received before billing (Liability)',
            self::PURCHASE => 'Expenses from vendor purchases',
            self::PURCHASE_CREDIT_MEMO => 'Returns to vendors for credit',
            self::PURCHASE_PREPAYMENT => 'Payments made to vendors before delivery (Asset)',
            self::COGS => 'Recognition of product cost at time of sale',
            self::INVENTORY_ADJUSTMENT => 'Corrections to stock levels and value',
            self::DIRECT_COST_APPLIED => 'Landed costs and item charges applied',
            self::OVERHEAD_APPLIED => 'Manufacturing and indirect cost allocation',
            self::PURCHASE_VARIANCE => 'Difference between standard and actual purchase price',
            self::PRODUCTION_VARIANCE => 'Difference between standard and actual manufacturing cost',
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
