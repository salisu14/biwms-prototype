<?php

namespace App\Policies;

use App\Models\BankAccount;
use App\Models\User;

class BankAccountPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'finance.bank_account';
    }

    protected function legacyKey(): string
    {
        return 'bank';
    }

    public function openingBalance(User $user, BankAccount $bankAccount): bool
    {
        return $this->canAny($user, ['finance.bank_account.opening_balance']);
    }
}
