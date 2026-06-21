<?php

namespace App\Policies;

class EmployeePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.employee';
    }

    protected function legacyKey(): string
    {
        return 'employee';
    }
}
