<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionPaymentApplicationPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_payment_application';
    }

    protected function legacyKey(): string
    {
        return 'commission_payment_application';
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
