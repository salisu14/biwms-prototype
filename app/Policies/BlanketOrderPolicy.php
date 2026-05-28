<?php

namespace App\Policies;

class BlanketOrderPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'procurement.blanket_order';
    }

    protected function legacyKey(): string
    {
        return 'blanket_order';
    }
}
