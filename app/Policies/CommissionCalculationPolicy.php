<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionCalculationPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_calculation';
    }

    protected function legacyKey(): string
    {
        return 'commission_calculation';
    }

    public function update(User $user, mixed $record): bool
    {
        return false;
    }

    public function delete(User $user, mixed $record): bool
    {
        return false;
    }

    public function calculate(User $user): bool
    {
        return $user->can($this->permissionPrefix().'.calculate');
    }

    public function recalculate(User $user, mixed $record): bool
    {
        return $user->can($this->permissionPrefix().'.recalculate');
    }

    public function reverse(User $user, mixed $record): bool
    {
        return $user->can($this->permissionPrefix().'.reverse');
    }
}
