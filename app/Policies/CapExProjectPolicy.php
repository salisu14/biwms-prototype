<?php

namespace App\Policies;

class CapExProjectPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'project.capex_project';
    }

    protected function legacyKey(): string
    {
        return 'capex_project';
    }
}
