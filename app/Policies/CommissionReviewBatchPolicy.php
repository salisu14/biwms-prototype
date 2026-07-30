<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionReviewBatchPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_review_batch';
    }

    protected function legacyKey(): string
    {
        return 'commission_review_batch';
    }

    public function generate(User $user): bool
    {
        return $user->can('sales.commission_review_batch.generate');
    }

    public function review(User $user): bool
    {
        return $user->can('sales.commission_review_batch.review');
    }

    public function submit(User $user): bool
    {
        return $user->can('sales.commission_review_batch.submit');
    }

    public function approve(User $user): bool
    {
        return $user->can('sales.commission_review_batch.approve');
    }

    public function reject(User $user): bool
    {
        return $user->can('sales.commission_review_batch.reject');
    }

    public function lock(User $user): bool
    {
        return $user->can('sales.commission_review_batch.lock');
    }

    public function reopen(User $user): bool
    {
        return $user->can('sales.commission_review_batch.reopen');
    }

    public function export(User $user): bool
    {
        return $user->can('sales.commission_review_batch.export');
    }
}
