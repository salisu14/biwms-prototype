<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeAttendanceDay;
use App\Models\User;

class EmployeeAttendanceDayPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.attendance_register';
    }

    protected function legacyKey(): string
    {
        return 'attendance_register';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || ($record instanceof EmployeeAttendanceDay && $user->can('hr.my_attendance.view') && $user->employee_id === $record->employee_id)
            || ($record instanceof EmployeeAttendanceDay && $this->managesEmployee($user, $record));
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $record): bool
    {
        return false;
    }

    public function delete(User $user, mixed $record): bool
    {
        return false;
    }

    private function managesEmployee(User $user, EmployeeAttendanceDay $record): bool
    {
        return $user->employee_id !== null
            && (int) ($record->employee?->department?->manager_id ?? 0) === (int) $user->employee_id;
    }
}
