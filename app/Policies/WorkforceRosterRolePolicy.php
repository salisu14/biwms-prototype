<?php

declare(strict_types=1);

namespace App\Policies;

class WorkforceRosterRolePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.workforce_roster_role';
    }

    protected function legacyKey(): string
    {
        return 'workforce_roster_role';
    }
}
