<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendanceReviewPeriod;
use App\Models\User;

class AttendanceReviewPeriodPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.attendance_review_period';
    }

    protected function legacyKey(): string
    {
        return 'attendance_review_period';
    }

    public function submit(User $user, AttendanceReviewPeriod $record): bool
    {
        return in_array($record->status, [AttendanceReviewPeriod::STATUS_DRAFT, AttendanceReviewPeriod::STATUS_OPEN, AttendanceReviewPeriod::STATUS_REOPENED], true)
            && $user->can('hr.attendance_review_period.submit');
    }

    public function approve(User $user, AttendanceReviewPeriod $record): bool
    {
        return $record->status === AttendanceReviewPeriod::STATUS_UNDER_REVIEW
            && $user->can('hr.attendance_review_period.approve');
    }

    public function lock(User $user, AttendanceReviewPeriod $record): bool
    {
        return $record->status === AttendanceReviewPeriod::STATUS_APPROVED
            && $user->can('hr.attendance_review_period.lock');
    }

    public function reopen(User $user, AttendanceReviewPeriod $record): bool
    {
        return in_array($record->status, [AttendanceReviewPeriod::STATUS_LOCKED, AttendanceReviewPeriod::STATUS_EXPORTED], true)
            && $user->can('hr.attendance_review_period.reopen');
    }

    public function export(User $user, AttendanceReviewPeriod $record): bool
    {
        return in_array($record->status, [AttendanceReviewPeriod::STATUS_APPROVED, AttendanceReviewPeriod::STATUS_LOCKED, AttendanceReviewPeriod::STATUS_EXPORTED], true)
            && $user->can('hr.attendance_review_period.export');
    }
}
