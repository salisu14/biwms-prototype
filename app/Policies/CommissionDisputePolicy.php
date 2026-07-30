<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionDisputePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_dispute';
    }

    protected function legacyKey(): string
    {
        return 'commission_dispute';
    }

    public function assign(User $user): bool
    {
        return $user->can('sales.commission_dispute.assign');
    }

    public function review(User $user): bool
    {
        return $user->can('sales.commission_dispute.review');
    }

    public function resolve(User $user): bool
    {
        return $user->can('sales.commission_dispute.resolve');
    }

    public function close(User $user): bool
    {
        return $user->can('sales.commission_dispute.close');
    }

    public function delete(User $user, mixed $record): bool
    {
        return false;
    }
}
