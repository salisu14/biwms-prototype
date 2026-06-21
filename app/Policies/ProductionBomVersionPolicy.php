<?php

namespace App\Policies;

class ProductionBomVersionPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'factory.production_bom_version';
    }

    protected function legacyKey(): string
    {
        return 'production_bom_version';
    }
}
