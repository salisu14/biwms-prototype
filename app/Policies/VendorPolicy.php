<?php

namespace App\Policies;

class VendorPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'procurement.vendor';
    }

    protected function legacyKey(): string
    {
        return 'vendor';
    }
}
