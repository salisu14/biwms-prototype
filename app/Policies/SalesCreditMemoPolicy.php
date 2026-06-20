<?php

namespace App\Policies;

use App\Models\SalesCreditMemo;
use App\Models\User;

class SalesCreditMemoPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.credit_memo';
    }

    protected function legacyKey(): string
    {
        return 'sales_credit_memo';
    }

    public function post(User $user, SalesCreditMemo $salesCreditMemo): bool
    {
        return $this->canAny($user, [
            'sales.credit_memo.post',
            'post:sales_credit_memo',
            'sales_credit_memo_post',
        ]);
    }
}
