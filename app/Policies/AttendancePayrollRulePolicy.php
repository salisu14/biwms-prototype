<?php

declare(strict_types=1);

namespace App\Policies;

class AttendancePayrollRulePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'payroll.attendance_rule';
    }

    protected function legacyKey(): string
    {
        return 'attendance_rule';
    }
}
