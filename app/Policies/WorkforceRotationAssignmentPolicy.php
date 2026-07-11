<?php

declare(strict_types=1);

namespace App\Policies;

class WorkforceRotationAssignmentPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.workforce_rotation_assignment';
    }

    protected function legacyKey(): string
    {
        return 'workforce_rotation_assignment';
    }
}
