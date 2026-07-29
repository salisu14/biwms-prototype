<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Manufacturing\ProductionOperationExecution;
use App\Models\User;

class ProductionOperationExecutionPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'factory.production_operation_execution';
    }

    protected function legacyKey(): string
    {
        return 'production_operation_execution';
    }

    public function start(User $user, ProductionOperationExecution $record): bool
    {
        return $this->canOperateAssignedExecution($user, $record) || $user->can('factory.production_operation_execution.start');
    }

    public function pause(User $user, ProductionOperationExecution $record): bool
    {
        return $this->canOperateAssignedExecution($user, $record) || $user->can('factory.production_operation_execution.pause');
    }

    public function resume(User $user, ProductionOperationExecution $record): bool
    {
        return $this->canOperateAssignedExecution($user, $record) || $user->can('factory.production_operation_execution.resume');
    }

    public function complete(User $user, ProductionOperationExecution $record): bool
    {
        return $this->canOperateAssignedExecution($user, $record) || $user->can('factory.production_operation_execution.complete');
    }

    public function submit(User $user, ProductionOperationExecution $record): bool
    {
        return $user->can('factory.production_operation_execution.submit');
    }

    public function post(User $user, ProductionOperationExecution $record): bool
    {
        return $user->can('factory.production_operation_execution.post');
    }

    public function reverse(User $user, ProductionOperationExecution $record): bool
    {
        return $user->can('factory.production_operation_execution.reverse');
    }

    public function overrideSequence(User $user, ProductionOperationExecution $record): bool
    {
        return $user->can('factory.production_operation_execution.sequence_override');
    }

    public function correctTime(User $user, ProductionOperationExecution $record): bool
    {
        return $user->can('factory.production_time.correct');
    }

    public function recordScrap(User $user, ProductionOperationExecution $record): bool
    {
        return $this->canOperateAssignedExecution($user, $record) || $user->can('factory.production_scrap.record');
    }

    public function approveScrap(User $user, ProductionOperationExecution $record): bool
    {
        return $user->can('factory.production_scrap.approve');
    }

    public function recordRework(User $user, ProductionOperationExecution $record): bool
    {
        return $this->canOperateAssignedExecution($user, $record) || $user->can('factory.production_rework.record');
    }

    public function recordDowntime(User $user, ProductionOperationExecution $record): bool
    {
        return $this->canOperateAssignedExecution($user, $record) || $user->can('factory.production_downtime.record');
    }

    public function approveRework(User $user, ProductionOperationExecution $record): bool
    {
        return $user->can('factory.production_rework.approve');
    }

    public function placeQualityHold(User $user, ProductionOperationExecution $record): bool
    {
        return $user->can('factory.production_quality.record');
    }

    public function releaseQualityHold(User $user, ProductionOperationExecution $record): bool
    {
        return $user->can('factory.production_quality.release_hold');
    }

    private function canOperateAssignedExecution(User $user, ProductionOperationExecution $record): bool
    {
        $employeeId = $user->employee_id;

        return $employeeId !== null
            && (
                (int) $record->operator_employee_id === (int) $employeeId
                || $record->assignments()->where('employee_id', $employeeId)->exists()
            );
    }
}
