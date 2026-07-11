<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class PerformanceAppraisalHistoryPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.performance_history';
    }

    protected function legacyKey(): string
    {
        return 'performance_history';
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
