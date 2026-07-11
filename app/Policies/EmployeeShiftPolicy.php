<?php

declare(strict_types=1);

namespace App\Policies;

class EmployeeShiftPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.employee_shift';
    }

    protected function legacyKey(): string
    {
        return 'employee_shift';
    }
}
