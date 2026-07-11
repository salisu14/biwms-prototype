<?php

declare(strict_types=1);

namespace App\Policies;

class PerformanceGoalPlanPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.performance_goal_plan';
    }

    protected function legacyKey(): string
    {
        return 'performance_goal_plan';
    }
}
