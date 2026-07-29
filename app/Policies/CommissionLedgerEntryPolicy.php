<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionLedgerEntryPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_ledger';
    }

    protected function legacyKey(): string
    {
        return 'commission_ledger';
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

    public function export(User $user): bool
    {
        return $user->can($this->permissionPrefix().'.export');
    }
}
