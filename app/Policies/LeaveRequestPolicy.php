<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\LeaveRequest;
use App\Models\User;

class LeaveRequestPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.leave_request';
    }

    protected function legacyKey(): string
    {
        return 'leave_request';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || $this->ownsRequest($user, $record)
            || $this->managesRequest($user, $record);
    }

    public function update(User $user, mixed $record): bool
    {
        if ($record instanceof LeaveRequest && $this->ownsRequest($user, $record)) {
            return $record->status === LeaveRequest::STATUS_DRAFT;
        }

        return parent::update($user, $record);
    }

    public function submit(User $user, LeaveRequest $request): bool
    {
        return ($this->ownsRequest($user, $request) && $request->status === LeaveRequest::STATUS_DRAFT)
            || $user->can('hr.leave_request.submit');
    }

    public function approve(User $user, LeaveRequest $request): bool
    {
        return $user->can('hr.leave_approval.approve')
            || ($this->managesRequest($user, $request) && $user->employee_id !== $request->employee_id);
    }

    public function reject(User $user, LeaveRequest $request): bool
    {
        return $user->can('hr.leave_approval.reject')
            || ($this->managesRequest($user, $request) && $user->employee_id !== $request->employee_id);
    }

    public function cancel(User $user, LeaveRequest $request): bool
    {
        return $user->can('hr.leave_request.cancel') || $this->ownsRequest($user, $request);
    }

    private function ownsRequest(User $user, mixed $record): bool
    {
        return $record instanceof LeaveRequest
            && $user->employee_id !== null
            && (int) $user->employee_id === (int) $record->employee_id;
    }

    private function managesRequest(User $user, mixed $record): bool
    {
        if (! $record instanceof LeaveRequest || $user->employee_id === null) {
            return false;
        }

        $record->loadMissing('employee.department');

        return (int) ($record->employee?->department?->manager_id ?? 0) === (int) $user->employee_id;
    }
}
