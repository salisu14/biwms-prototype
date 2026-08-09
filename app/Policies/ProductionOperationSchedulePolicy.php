<?php

declare(strict_types=1);

namespace App\Policies;

class ProductionOperationSchedulePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'manufacturing.production_operation_schedule';
    }

    protected function legacyKey(): string
    {
        return 'production_operation_schedule';
    }
}
