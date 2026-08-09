<?php

declare(strict_types=1);

namespace App\Policies;

class ProductionSchedulingExceptionPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'manufacturing.production_scheduling_exception';
    }

    protected function legacyKey(): string
    {
        return 'production_scheduling_exception';
    }
}
