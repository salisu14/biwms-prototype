<?php

namespace App\Policies;

class PaymentPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'finance.payment';
    }

    protected function legacyKey(): string
    {
        return 'payment';
    }
}
