<?php

declare(strict_types=1);

namespace App\Policies;

class EmployeeLeaveEntitlementPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.leave_entitlement';
    }

    protected function legacyKey(): string
    {
        return 'leave_entitlement';
    }
}
