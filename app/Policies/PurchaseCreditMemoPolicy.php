<?php

namespace App\Policies;

use App\Models\PurchaseCreditMemo;
use App\Models\User;

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

    public function post(User $user, PurchaseCreditMemo $purchaseCreditMemo): bool
    {
        return $this->canAny($user, [
            'purchase.credit_memo.post',
            'procurement.purchase_credit_memo.post',
            'post:purchase_credit_memo',
            'purchase_credit_memo_post',
        ]);
    }
}
