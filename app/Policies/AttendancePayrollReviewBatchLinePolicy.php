<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendancePayrollReviewBatchLine;
use App\Models\User;

class AttendancePayrollReviewBatchLinePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'payroll.attendance_adjustment';
    }

    protected function legacyKey(): string
    {
        return 'attendance_adjustment';
    }

    public function override(User $user, AttendancePayrollReviewBatchLine $record): bool
    {
        return $record->status !== AttendancePayrollReviewBatchLine::STATUS_POSTED
            && $user->can('payroll.attendance_adjustment.override');
    }

    public function reverse(User $user, AttendancePayrollReviewBatchLine $record): bool
    {
        return $record->status === AttendancePayrollReviewBatchLine::STATUS_POSTED
            && $user->can('payroll.attendance_adjustment.reverse');
    }
}
