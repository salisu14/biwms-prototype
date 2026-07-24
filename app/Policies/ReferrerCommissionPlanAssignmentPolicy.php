<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class ReferrerCommissionPlanAssignmentPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.referrer_commission_plan_assignment';
    }

    protected function legacyKey(): string
    {
        return 'referrer_commission_plan_assignment';
    }

    public function assign(User $user, mixed $record = null): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.assign', $this->permissionPrefix().'.create']);
    }

    public function change(User $user, mixed $record = null): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.change']);
    }

    public function end(User $user, mixed $record = null): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.end']);
    }

    public function cancel(User $user, mixed $record = null): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.cancel']);
    }
}
