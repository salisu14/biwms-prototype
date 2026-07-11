<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendanceReviewItem;
use App\Models\User;

class AttendanceReviewItemPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.attendance_review_item';
    }

    protected function legacyKey(): string
    {
        return 'attendance_review_item';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || ($record instanceof AttendanceReviewItem && $user->can('hr.attendance_review_item.view_own') && $user->employee_id === $record->employee_id)
            || ($record instanceof AttendanceReviewItem && $user->can('hr.attendance_review_item.view_team') && $this->managesEmployee($user, $record));
    }

    public function resolve(User $user, AttendanceReviewItem $record): bool
    {
        return $record->review_status !== AttendanceReviewItem::STATUS_RESOLVED
            && $user->can('hr.attendance_review_item.resolve');
    }

    public function waive(User $user, AttendanceReviewItem $record): bool
    {
        return $record->review_status !== AttendanceReviewItem::STATUS_WAIVED
            && $user->can('hr.attendance_review_item.waive');
    }

    public function escalate(User $user, AttendanceReviewItem $record): bool
    {
        return $record->review_status !== AttendanceReviewItem::STATUS_ESCALATED
            && $user->can('hr.attendance_review_item.escalate');
    }

    public function approvePayrollImpact(User $user, AttendanceReviewItem $record): bool
    {
        return $record->review_status === AttendanceReviewItem::STATUS_RESOLVED
            && $user->can('hr.attendance_review_item.approve_payroll_impact');
    }

    private function managesEmployee(User $user, AttendanceReviewItem $record): bool
    {
        return $user->employee_id !== null
            && (int) ($record->employee?->department?->manager_id ?? 0) === (int) $user->employee_id;
    }
}
