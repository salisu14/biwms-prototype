<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class CommissionPaymentLinePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.commission_payment_line';
    }

    protected function legacyKey(): string
    {
        return 'commission_payment_line';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function updateDraft(User $user): bool
    {
        return $user->can('sales.commission_payment_line.update_draft');
    }

    public function delete(User $user, mixed $record): bool
    {
        return false;
    }
}
