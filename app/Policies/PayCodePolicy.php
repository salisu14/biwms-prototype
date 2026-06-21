<?php

namespace App\Policies;

class PayCodePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.pay_code';
    }

    protected function legacyKey(): string
    {
        return 'pay_code';
    }
}
