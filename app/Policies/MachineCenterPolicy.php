<?php

namespace App\Policies;

class MachineCenterPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'factory.machine_center';
    }

    protected function legacyKey(): string
    {
        return 'machine_center';
    }
}
