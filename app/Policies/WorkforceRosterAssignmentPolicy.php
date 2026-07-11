<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkforceRosterAssignment;

class WorkforceRosterAssignmentPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.workforce_roster_assignment';
    }

    protected function legacyKey(): string
    {
        return 'workforce_roster_assignment';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || ($record instanceof WorkforceRosterAssignment && $user->can('hr.my_roster.view') && $user->employee_id === $record->employee_id);
    }

    public function cancel(User $user, WorkforceRosterAssignment $record): bool
    {
        return $record->status !== WorkforceRosterAssignment::STATUS_CANCELLED
            && $user->can('hr.workforce_roster_assignment.cancel');
    }

    public function replace(User $user, WorkforceRosterAssignment $record): bool
    {
        return $record->status !== WorkforceRosterAssignment::STATUS_REPLACED
            && $user->can('hr.workforce_roster_assignment.replace');
    }
}
