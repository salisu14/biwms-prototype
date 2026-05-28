<?php

namespace App\Policies;

class WorkCenterGroupPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'factory.work_center_group';
    }

    protected function legacyKey(): string
    {
        return 'work_center_group';
    }
}
