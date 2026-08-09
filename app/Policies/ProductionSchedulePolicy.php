<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Manufacturing\ProductionSchedule;
use App\Models\User;

class ProductionSchedulePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'manufacturing.production_schedule';
    }

    protected function legacyKey(): string
    {
        return 'production_schedule';
    }

    public function generate(User $user): bool
    {
        return $this->canAny($user, ['manufacturing.production_schedule.generate']);
    }

    public function approve(User $user, ProductionSchedule $productionSchedule): bool
    {
        return $this->canAny($user, ['manufacturing.production_schedule.approve']);
    }

    public function reschedule(User $user, ProductionSchedule $productionSchedule): bool
    {
        return $this->canAny($user, ['manufacturing.production_schedule.reschedule']);
    }

    public function reconcile(User $user): bool
    {
        return $this->canAny($user, ['manufacturing.production_schedule.reconcile']);
    }
}
