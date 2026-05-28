<?php

namespace App\Policies;

class PurchaseCreditMemoPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'procurement.purchase_credit_memo';
    }

    protected function legacyKey(): string
    {
        return 'purchase_credit_memo';
    }
}
