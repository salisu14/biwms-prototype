<?php

declare(strict_types=1);

namespace App\Policies;

class ProductionAlternateResourcePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'manufacturing.production_alternate_resource';
    }

    protected function legacyKey(): string
    {
        return 'production_alternate_resource';
    }
}
