<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionHoldPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_hold';
    }

    protected function legacyKey(): string
    {
        return 'commission_hold';
    }

    public function release(User $user): bool
    {
        return $user->can('sales.commission_hold.release');
    }

    public function cancel(User $user): bool
    {
        return $user->can('sales.commission_hold.cancel');
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
