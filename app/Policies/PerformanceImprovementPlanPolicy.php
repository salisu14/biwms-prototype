<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PerformanceImprovementPlan;
use App\Models\User;

class PerformanceImprovementPlanPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.performance_improvement_plan';
    }

    protected function legacyKey(): string
    {
        return 'performance_improvement_plan';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || ($record instanceof PerformanceImprovementPlan && $user->employee_id !== null && (int) $record->employee_id === (int) $user->employee_id && $user->can('hr.my_performance_improvement_plan.view'));
    }

    public function activate(User $user, PerformanceImprovementPlan $record): bool
    {
        return $record->status !== PerformanceImprovementPlan::STATUS_ACTIVE && $user->can('hr.performance_improvement_plan.activate');
    }

    public function complete(User $user, PerformanceImprovementPlan $record): bool
    {
        return $record->status === PerformanceImprovementPlan::STATUS_ACTIVE && $user->can('hr.performance_improvement_plan.complete');
    }
}
