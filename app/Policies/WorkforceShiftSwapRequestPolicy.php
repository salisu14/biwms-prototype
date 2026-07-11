<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkforceShiftSwapRequest;

class WorkforceShiftSwapRequestPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.shift_swap_request';
    }

    protected function legacyKey(): string
    {
        return 'shift_swap_request';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || ($record instanceof WorkforceShiftSwapRequest && $user->can('hr.my_shift_swap.view') && $user->employee_id === $record->requester_employee_id);
    }

    public function accept(User $user, WorkforceShiftSwapRequest $record): bool
    {
        return $record->target_employee_id === $user->employee_id && $user->can('hr.shift_swap_request.accept');
    }

    public function approve(User $user, WorkforceShiftSwapRequest $record): bool
    {
        return $record->accepted_by !== $user->id && $user->can('hr.shift_swap_request.approve');
    }
}
