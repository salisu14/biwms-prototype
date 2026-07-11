<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeeWorkAvailability;
use App\Models\User;

class EmployeeWorkAvailabilityPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.employee_work_availability';
    }

    protected function legacyKey(): string
    {
        return 'employee_work_availability';
    }

    public function view(User $user, mixed $record): bool
    {
        if ($record instanceof EmployeeWorkAvailability && $record->is_confidential && ! $user->can('hr.employee_work_availability.view_confidential')) {
            return false;
        }

        return parent::view($user, $record)
            || ($record instanceof EmployeeWorkAvailability && $user->can('hr.my_availability.view') && $user->employee_id === $record->employee_id);
    }

    public function approve(User $user, EmployeeWorkAvailability $record): bool
    {
        return $record->status === EmployeeWorkAvailability::STATUS_SUBMITTED && $user->can('hr.employee_work_availability.approve');
    }

    public function reject(User $user, EmployeeWorkAvailability $record): bool
    {
        return $record->status === EmployeeWorkAvailability::STATUS_SUBMITTED && $user->can('hr.employee_work_availability.reject');
    }
}
