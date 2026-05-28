<?php

namespace App\Policies;

class RoutingVersionPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'factory.routing_version';
    }

    protected function legacyKey(): string
    {
        return 'routing_version';
    }
}
