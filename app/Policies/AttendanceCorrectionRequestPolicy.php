<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\AttendanceCorrectionRequest;
use App\Models\User;

class AttendanceCorrectionRequestPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.attendance_correction';
    }

    protected function legacyKey(): string
    {
        return 'attendance_correction';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || ($record instanceof AttendanceCorrectionRequest && $user->employee_id === $record->employee_id)
            || ($record instanceof AttendanceCorrectionRequest && $this->managesEmployee($user, $record));
    }

    public function approve(User $user, AttendanceCorrectionRequest $record): bool
    {
        return $record->status === AttendanceCorrectionRequest::STATUS_SUBMITTED
            && $user->can('hr.attendance_correction.approve');
    }

    public function reject(User $user, AttendanceCorrectionRequest $record): bool
    {
        return $record->status === AttendanceCorrectionRequest::STATUS_SUBMITTED
            && $user->can('hr.attendance_correction.reject');
    }

    private function managesEmployee(User $user, AttendanceCorrectionRequest $record): bool
    {
        return $user->employee_id !== null
            && (int) ($record->employee?->department?->manager_id ?? 0) === (int) $user->employee_id;
    }
}
