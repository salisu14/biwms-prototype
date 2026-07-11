<?php

declare(strict_types=1);

namespace App\Policies;

class WorkforceSchedulingRulePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.workforce_scheduling_rule';
    }

    protected function legacyKey(): string
    {
        return 'workforce_scheduling_rule';
    }
}
