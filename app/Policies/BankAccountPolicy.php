<?php

namespace App\Policies;

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
}
