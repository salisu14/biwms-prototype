<?php

namespace App\Policies;

class PurchaseInvoicePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'procurement.purchase_invoice';
    }

    protected function legacyKey(): string
    {
        return 'purchase_invoice';
    }
}
