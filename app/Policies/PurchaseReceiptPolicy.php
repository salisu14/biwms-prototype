<?php

namespace App\Policies;

class PurchaseReceiptPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'procurement.purchase_receipt';
    }

    protected function legacyKey(): string
    {
        return 'purchase_receipt';
    }
}
