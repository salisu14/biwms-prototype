<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionPaymentBatchPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_payment_batch';
    }

    protected function legacyKey(): string
    {
        return 'commission_payment_batch';
    }

    public function prepare(User $user): bool
    {
        return $user->can('sales.commission_payment_batch.prepare');
    }

    public function submit(User $user): bool
    {
        return $user->can('sales.commission_payment_batch.submit');
    }

    public function approve(User $user): bool
    {
        return $user->can('sales.commission_payment_batch.approve');
    }

    public function reject(User $user): bool
    {
        return $user->can('sales.commission_payment_batch.reject');
    }

    public function post(User $user): bool
    {
        return $user->can('sales.commission_payment_batch.post');
    }

    public function cancel(User $user): bool
    {
        return $user->can('sales.commission_payment_batch.cancel');
    }

    public function reverse(User $user): bool
    {
        return $user->can('sales.commission_payment_batch.reverse');
    }

    public function export(User $user): bool
    {
        return $user->can('sales.commission_payment_batch.export');
    }
}
