<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionSettlementBatchPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_settlement_batch';
    }

    protected function legacyKey(): string
    {
        return 'commission_settlement_batch';
    }

    public function prepare(User $user): bool
    {
        return $user->can('sales.commission_settlement_batch.prepare');
    }

    public function submit(User $user): bool
    {
        return $user->can('sales.commission_settlement_batch.submit');
    }

    public function approve(User $user): bool
    {
        return $user->can('sales.commission_settlement_batch.approve');
    }

    public function reject(User $user): bool
    {
        return $user->can('sales.commission_settlement_batch.reject');
    }

    public function lock(User $user): bool
    {
        return $user->can('sales.commission_settlement_batch.lock');
    }

    public function cancel(User $user): bool
    {
        return $user->can('sales.commission_settlement_batch.cancel');
    }

    public function export(User $user): bool
    {
        return $user->can('sales.commission_settlement_batch.export');
    }
}
