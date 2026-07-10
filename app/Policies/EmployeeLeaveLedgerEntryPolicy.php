<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class EmployeeLeaveLedgerEntryPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.leave_ledger';
    }

    protected function legacyKey(): string
    {
        return 'leave_ledger';
    }

    public function create(User $user): bool
    {
        return $user->can('hr.leave_adjustment.post');
    }

    public function update(User $user, mixed $record): bool
    {
        return false;
    }

    public function delete(User $user, mixed $record): bool
    {
        return false;
    }
}
