<?php

namespace App\Policies;

class WarehouseReceiptPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'warehouse.receipt';
    }

    protected function legacyKey(): string
    {
        return 'warehouse_receipt';
    }
}
