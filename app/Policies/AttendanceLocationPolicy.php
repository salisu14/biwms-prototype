<?php

declare(strict_types=1);

namespace App\Policies;

class AttendanceLocationPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.attendance_location';
    }

    protected function legacyKey(): string
    {
        return 'attendance_location';
    }
}
