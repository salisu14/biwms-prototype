<?php

namespace App\Policies;

class PurchaseQuotePolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'procurement.purchase_quote';
    }

    protected function legacyKey(): string
    {
        return 'purchase_quote';
    }
}
