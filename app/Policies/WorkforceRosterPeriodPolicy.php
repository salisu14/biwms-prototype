<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkforceRosterPeriod;

class WorkforceRosterPeriodPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.workforce_roster_period';
    }

    protected function legacyKey(): string
    {
        return 'workforce_roster_period';
    }

    public function generate(User $user, WorkforceRosterPeriod $record): bool
    {
        return ! $record->isPublishedLike() && $user->can('hr.workforce_roster_period.generate');
    }

    public function publish(User $user, WorkforceRosterPeriod $record): bool
    {
        return ! $record->isPublishedLike() && $user->can('hr.workforce_roster_period.publish');
    }

    public function close(User $user, WorkforceRosterPeriod $record): bool
    {
        return $record->status === WorkforceRosterPeriod::STATUS_ACTIVE && $user->can('hr.workforce_roster_period.close');
    }

    public function reopen(User $user, WorkforceRosterPeriod $record): bool
    {
        return $record->isPublishedLike() && $user->can('hr.workforce_roster_period.reopen');
    }
}
