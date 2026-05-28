<?php

namespace App\Policies;

class PayrollPeriodPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.payroll_period';
    }

    protected function legacyKey(): string
    {
        return 'payroll_period';
    }
}
