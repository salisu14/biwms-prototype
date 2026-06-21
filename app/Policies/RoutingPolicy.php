<?php

namespace App\Policies;

class RoutingPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'factory.routing';
    }

    protected function legacyKey(): string
    {
        return 'routing';
    }
}
