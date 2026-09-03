<?php

namespace App\Policies;

use App\Models\User;

class VendorLedgerEntryPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'procurement.vendor_ledger_entry';
    }

    protected function legacyKey(): string
    {
        return 'vendor_ledger_entry';
    }

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
}
