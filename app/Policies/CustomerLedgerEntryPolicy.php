<?php

namespace App\Policies;

class CustomerLedgerEntryPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'finance.customer_ledger_entry';
    }

    protected function legacyKey(): string
    {
        return 'customer_ledger_entry';
    }
}
