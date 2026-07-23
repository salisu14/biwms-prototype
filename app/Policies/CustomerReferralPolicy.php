<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CustomerReferralPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.customer_referral';
    }

    protected function legacyKey(): string
    {
        return 'customer_referral';
    }

    public function assign(User $user): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.assign']);
    }

    public function change(User $user, mixed $record = null): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.change']);
    }

    public function approve(User $user, mixed $record = null): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.approve']);
    }

    public function suspend(User $user, mixed $record): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.suspend']);
    }

    public function reactivate(User $user, mixed $record): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.reactivate']);
    }

    public function end(User $user, mixed $record): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.end']);
    }

    public function cancel(User $user, mixed $record): bool
    {
        return $this->canAny($user, [$this->permissionPrefix().'.cancel']);
    }
}
