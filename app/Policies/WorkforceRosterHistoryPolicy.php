<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class WorkforceRosterHistoryPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.workforce_roster_history';
    }

    protected function legacyKey(): string
    {
        return 'workforce_roster_history';
    }

    public function create(User $user): bool
    {
        return false;
    }
}
