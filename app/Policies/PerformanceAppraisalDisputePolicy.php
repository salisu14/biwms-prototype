<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PerformanceAppraisalDispute;
use App\Models\User;

class PerformanceAppraisalDisputePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.performance_appraisal_dispute';
    }

    protected function legacyKey(): string
    {
        return 'performance_appraisal_dispute';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record)
            || ($record instanceof PerformanceAppraisalDispute && $user->employee_id !== null && (int) $record->employee_id === (int) $user->employee_id && $user->can('hr.my_appraisal.dispute'));
    }
}
