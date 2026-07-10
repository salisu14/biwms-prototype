<?php

declare(strict_types=1);

namespace App\Policies;

class LeaveTypePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.leave_type';
    }

    protected function legacyKey(): string
    {
        return 'leave_type';
    }
}
