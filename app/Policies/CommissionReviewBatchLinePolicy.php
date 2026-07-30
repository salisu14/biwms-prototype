<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionReviewBatchLinePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_review_batch';
    }

    protected function legacyKey(): string
    {
        return 'commission_review_batch_line';
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
