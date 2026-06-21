<?php

namespace App\Policies;

class WarehouseActivityPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'warehouse.activity';
    }

    protected function legacyKey(): string
    {
        return 'warehouse_activity';
    }
}
