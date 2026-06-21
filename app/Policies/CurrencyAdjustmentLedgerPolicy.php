<?php

namespace App\Policies;

class CurrencyAdjustmentLedgerPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'finance.currency_adjustment_ledger';
    }

    protected function legacyKey(): string
    {
        return 'currency_adjustment_ledger';
    }
}
