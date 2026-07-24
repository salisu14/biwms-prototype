<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class ReferralCommissionPlanPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.referral_commission_plan';
    }

    protected function legacyKey(): string
    {
        return 'referral_commission_plan';
    }

    public function activate(User $user, mixed $record = null): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.activate']);
    }

    public function inactivate(User $user, mixed $record = null): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.inactivate']);
    }

    public function archive(User $user, mixed $record = null): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.archive']);
    }
}
