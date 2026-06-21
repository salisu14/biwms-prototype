<?php

namespace App\Policies;

class WorkCenterPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'factory.work_center';
    }

    protected function legacyKey(): string
    {
        return 'work_center';
    }
}
