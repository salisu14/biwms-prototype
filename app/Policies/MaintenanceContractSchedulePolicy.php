<?php

namespace App\Policies;

class MaintenanceContractSchedulePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'service.dispatch';
    }

    protected function legacyKey(): string
    {
        return 'service_dispatch';
    }
}
