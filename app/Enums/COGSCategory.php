<?php

namespace App\Enums;

enum COGSCategory: string
{
    case MATERIALS = 'materials';
    case LABOR = 'labor';
    case OVERHEAD = 'overhead';
    case PURCHASE_ACCOUNT = 'purchase_account'; // Interim
    case DIRECT_COST_APPLIED = 'direct_cost_applied';
    case OVERHEAD_APPLIED = 'overhead_applied';
    case VARIANCE = 'variance';

    public function label(): string
    {
        return match ($this) {
            self::MATERIALS => 'COGS - Materials',
            self::LABOR => 'COGS - Direct Labor',
            self::OVERHEAD => 'COGS - Manufacturing Overhead',
            self::PURCHASE_ACCOUNT => 'Purchase Account (Interim)',
            self::DIRECT_COST_APPLIED => 'Direct Cost Applied',
            self::OVERHEAD_APPLIED => 'Overhead Applied',
            self::VARIANCE => 'COGS Variance',
        };
    }

    public function isInterim(): bool
    {
        return $this === self::PURCHASE_ACCOUNT;
    }
}
