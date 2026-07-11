<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendancePayrollReviewBatch;
use App\Models\User;

class AttendancePayrollReviewBatchPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'payroll.attendance_batch';
    }

    protected function legacyKey(): string
    {
        return 'attendance_batch';
    }

    public function generate(User $user): bool
    {
        return $user->can('payroll.attendance_batch.generate');
    }

    public function approve(User $user, AttendancePayrollReviewBatch $record): bool
    {
        return in_array($record->status, [AttendancePayrollReviewBatch::STATUS_DRAFT, AttendancePayrollReviewBatch::STATUS_PENDING_REVIEW], true)
            && $user->can('payroll.attendance_batch.approve');
    }

    public function reject(User $user, AttendancePayrollReviewBatch $record): bool
    {
        return in_array($record->status, [AttendancePayrollReviewBatch::STATUS_DRAFT, AttendancePayrollReviewBatch::STATUS_PENDING_REVIEW], true)
            && $user->can('payroll.attendance_batch.reject');
    }

    public function post(User $user, AttendancePayrollReviewBatch $record): bool
    {
        return $record->status === AttendancePayrollReviewBatch::STATUS_APPROVED
            && $user->can('payroll.attendance_batch.post');
    }

    public function reverse(User $user, AttendancePayrollReviewBatch $record): bool
    {
        return in_array($record->status, [AttendancePayrollReviewBatch::STATUS_POSTED, AttendancePayrollReviewBatch::STATUS_PARTIALLY_POSTED], true)
            && $user->can('payroll.attendance_batch.reverse');
    }
}
