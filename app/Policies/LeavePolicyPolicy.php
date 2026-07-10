<?php

declare(strict_types=1);

namespace App\Policies;

class LeavePolicyPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.leave_policy';
    }

    protected function legacyKey(): string
    {
        return 'leave_policy';
    }
}
