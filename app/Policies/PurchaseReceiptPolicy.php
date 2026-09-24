<?php

namespace App\Policies;

use App\Models\PurchaseReceipt;
use App\Models\User;

class PurchaseReceiptPolicy extends AbstractPermissionPolicy
{
    public function reorder(User $user): bool
    {
        return $this->update($user, PurchaseReceipt::class);
    }

    protected function permissionPrefix(): string
    {
        return 'procurement.purchase_receipt';
    }

    protected function legacyKey(): string
    {
        return 'purchase_receipt';
    }
}
