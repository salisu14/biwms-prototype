<?php

namespace App\Policies;

class WarehouseShipmentPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'warehouse.shipment';
    }

    protected function legacyKey(): string
    {
        return 'warehouse_shipment';
    }
}
