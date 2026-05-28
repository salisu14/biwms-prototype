<?php

namespace App\Policies;

class MaintenanceContractPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'service.maintenance_contract';
    }

    protected function legacyKey(): string
    {
        return 'maintenance_contract';
    }
}
