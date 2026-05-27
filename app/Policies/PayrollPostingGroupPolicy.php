<?php

namespace App\Policies;

class PayrollPostingGroupPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.payroll_posting_group';
    }

    protected function legacyKey(): string
    {
        return 'payroll_posting_group';
    }
}
