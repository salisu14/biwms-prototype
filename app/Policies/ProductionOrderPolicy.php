<?php

namespace App\Policies;

class ProductionOrderPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'factory.production_order';
    }

    protected function legacyKey(): string
    {
        return 'production_order';
    }
}
