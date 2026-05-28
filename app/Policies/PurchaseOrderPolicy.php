<?php

namespace App\Policies;

class PurchaseOrderPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'procurement.purchase_order';
    }

    protected function legacyKey(): string
    {
        return 'purchase_order';
    }
}
