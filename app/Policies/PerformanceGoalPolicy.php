<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PerformanceGoal;
use App\Models\User;

class PerformanceGoalPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.performance_goal';
    }

    protected function legacyKey(): string
    {
        return 'performance_goal';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || ($record instanceof PerformanceGoal && $user->employee_id !== null && (int) $record->employee_id === (int) $user->employee_id && $user->can('hr.my_goal.view'));
    }

    public function update(User $user, mixed $record): bool
    {
        return parent::update($user, $record)
            || ($record instanceof PerformanceGoal && $record->status === PerformanceGoal::STATUS_DRAFT && $user->employee_id !== null && (int) $record->employee_id === (int) $user->employee_id && $user->can('hr.my_goal.update'));
    }

    public function approve(User $user, PerformanceGoal $record): bool
    {
        return $user->can('hr.performance_goal.approve');
    }

    public function revise(User $user, PerformanceGoal $record): bool
    {
        return $user->can('hr.performance_goal.revise');
    }
}
