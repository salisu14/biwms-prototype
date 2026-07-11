<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OvertimeApproval;
use App\Models\User;

class OvertimeApprovalPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.overtime_approval';
    }

    protected function legacyKey(): string
    {
        return 'overtime_approval';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || ($record instanceof OvertimeApproval && $this->managesEmployee($user, $record));
    }

    public function approve(User $user, OvertimeApproval $record): bool
    {
        return $record->status === OvertimeApproval::STATUS_SUBMITTED
            && $user->can('hr.overtime_approval.approve');
    }

    public function reject(User $user, OvertimeApproval $record): bool
    {
        return $record->status === OvertimeApproval::STATUS_SUBMITTED
            && $user->can('hr.overtime_approval.reject');
    }

    private function managesEmployee(User $user, OvertimeApproval $record): bool
    {
        return $user->employee_id !== null
            && (int) ($record->employee?->department?->manager_id ?? 0) === (int) $user->employee_id;
    }
}
