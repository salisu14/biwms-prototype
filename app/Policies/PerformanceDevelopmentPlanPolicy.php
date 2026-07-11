<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PerformanceDevelopmentPlan;
use App\Models\User;

class PerformanceDevelopmentPlanPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.performance_development_plan';
    }

    protected function legacyKey(): string
    {
        return 'performance_development_plan';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || ($record instanceof PerformanceDevelopmentPlan && $user->employee_id !== null && (int) $record->employee_id === (int) $user->employee_id && $user->can('hr.my_development_plan.view'));
    }
}
