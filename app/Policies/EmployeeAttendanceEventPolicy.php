<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeAttendanceEvent;
use App\Models\User;

class EmployeeAttendanceEventPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.attendance_event';
    }

    protected function legacyKey(): string
    {
        return 'attendance_event';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || ($record instanceof EmployeeAttendanceEvent && $user->can('hr.my_attendance.view') && $user->employee_id === $record->employee_id)
            || ($record instanceof EmployeeAttendanceEvent && $this->managesEmployee($user, $record));
    }

    public function update(User $user, mixed $record): bool
    {
        return false;
    }

    public function delete(User $user, mixed $record): bool
    {
        return false;
    }

    public function forceDelete(User $user, mixed $record): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    private function managesEmployee(User $user, EmployeeAttendanceEvent $record): bool
    {
        return $user->employee_id !== null
            && (int) ($record->employee?->department?->manager_id ?? 0) === (int) $user->employee_id;
    }
}
