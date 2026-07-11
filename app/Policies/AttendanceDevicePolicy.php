<?php

declare(strict_types=1);

namespace App\Policies;

class AttendanceDevicePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.attendance_device';
    }

    protected function legacyKey(): string
    {
        return 'attendance_device';
    }
}
