<?php

namespace App\Policies;

class WarehousePutawayPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'warehouse.putaway';
    }

    protected function legacyKey(): string
    {
        return 'warehouse_putaway';
    }
}
