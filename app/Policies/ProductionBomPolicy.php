<?php

namespace App\Policies;

class ProductionBomPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'factory.production_bom';
    }

    protected function legacyKey(): string
    {
        return 'production_bom';
    }
}
