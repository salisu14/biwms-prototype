<?php

declare(strict_types=1);

namespace App\Policies;

class EmployeeWorkScheduleAssignmentPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.work_schedule';
    }

    protected function legacyKey(): string
    {
        return 'work_schedule';
    }
}
