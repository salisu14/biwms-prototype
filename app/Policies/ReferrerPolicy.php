<?php

declare(strict_types=1);

namespace App\Policies;

class ReferrerPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.referrer';
    }

    protected function legacyKey(): string
    {
        return 'referrer';
    }
}
