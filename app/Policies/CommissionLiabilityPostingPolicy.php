<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionLiabilityPostingPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_liability';
    }

    protected function legacyKey(): string
    {
        return 'commission_liability';
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

    public function post(User $user): bool
    {
        return $user->can('sales.commission_liability.post');
    }

    public function reverse(User $user): bool
    {
        return $user->can('sales.commission_liability.reverse');
    }
}
