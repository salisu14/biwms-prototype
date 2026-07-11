<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PerformanceAppraisal;
use App\Models\User;

class PerformanceAppraisalPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.performance_appraisal';
    }

    protected function legacyKey(): string
    {
        return 'performance_appraisal';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || $this->isOwnAppraisal($user, $record)
            || $this->isAssignedManager($user, $record);
    }

    public function selfAssess(User $user, PerformanceAppraisal $record): bool
    {
        return $this->isOwnAppraisal($user, $record) && $user->can('hr.my_self_assessment.submit');
    }

    public function managerAssess(User $user, PerformanceAppraisal $record): bool
    {
        return $this->isAssignedManager($user, $record) && $user->can('hr.team_performance.assess');
    }

    public function moderate(User $user, PerformanceAppraisal $record): bool
    {
        return $user->can('hr.performance_appraisal.moderate');
    }

    public function finalize(User $user, PerformanceAppraisal $record): bool
    {
        return ! $record->isFinalizedLike() && $user->can('hr.performance_appraisal.finalize');
    }

    public function acknowledge(User $user, PerformanceAppraisal $record): bool
    {
        return $this->isOwnAppraisal($user, $record) && $user->can('hr.my_appraisal.acknowledge');
    }

    private function isOwnAppraisal(User $user, mixed $record): bool
    {
        return $record instanceof PerformanceAppraisal
            && $user->employee_id !== null
            && (int) $record->employee_id === (int) $user->employee_id
            && $user->can('hr.my_performance.view');
    }

    private function isAssignedManager(User $user, mixed $record): bool
    {
        return $record instanceof PerformanceAppraisal
            && $user->employee_id !== null
            && (int) $record->manager_employee_id === (int) $user->employee_id
            && $user->can('hr.team_performance.view');
    }
}
