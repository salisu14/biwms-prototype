<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionReviewPeriodPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_review_period';
    }

    protected function legacyKey(): string
    {
        return 'commission_review_period';
    }

    public function open(User $user): bool
    {
        return $user->can('sales.commission_review_period.open');
    }

    public function submit(User $user): bool
    {
        return $user->can('sales.commission_review_period.submit');
    }

    public function approve(User $user): bool
    {
        return $user->can('sales.commission_review_period.approve');
    }

    public function lock(User $user): bool
    {
        return $user->can('sales.commission_review_period.lock');
    }

    public function reopen(User $user): bool
    {
        return $user->can('sales.commission_review_period.reopen');
    }

    public function cancel(User $user): bool
    {
        return $user->can('sales.commission_review_period.cancel');
    }
}
