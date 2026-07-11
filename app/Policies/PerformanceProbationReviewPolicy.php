<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\PerformanceProbationReview;
use App\Models\User;

class PerformanceProbationReviewPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.performance_probation_review';
    }

    protected function legacyKey(): string
    {
        return 'performance_probation_review';
    }

    public function decide(User $user, PerformanceProbationReview $record): bool
    {
        return $user->can('hr.performance_probation_review.decide');
    }
}
