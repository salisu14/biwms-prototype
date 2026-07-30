<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionSettlementAllocationPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_settlement_batch';
    }

    protected function legacyKey(): string
    {
        return 'commission_settlement_allocation';
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
}
