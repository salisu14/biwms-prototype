<?php

namespace App\Policies;

use App\Models\User;

class CustomerLedgerEntryPolicy extends AbstractPermissionPolicy
{
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $record): bool
    {
        return false;
    }

    public function delete(User $user, mixed $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    protected function permissionPrefix(): string
    {
        return 'finance.customer_ledger_entry';
    }

    protected function legacyKey(): string
    {
        return 'customer_ledger_entry';
    }
}
