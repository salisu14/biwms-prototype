<?php

declare(strict_types=1);

namespace App\Policies;

use App\Contracts\ApprovableStatus;
use App\Enums\ApprovalStatus;
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

    public function update(User $user, mixed $record): bool
    {
        return $record instanceof SalesCreditMemo
            && ! $record->isPosted()
            && parent::update($user, $record);
    }

    public function delete(User $user, mixed $record): bool
    {
        return $record instanceof SalesCreditMemo
            && ! $record->isPosted()
            && parent::delete($user, $record);
    }

    public function submit(User $user, SalesCreditMemo $salesCreditMemo): bool
    {
        return $salesCreditMemo->status instanceof ApprovableStatus
            && $salesCreditMemo->status->canSubmitForApproval()
            && $this->canAny($user, [
                'sales.credit_memo.submit',
                'submit:sales_credit_memo',
                'sales_credit_memo_submit',
            ]);
    }

    public function post(User $user, SalesCreditMemo $salesCreditMemo): bool
    {
        return $salesCreditMemo->status === ApprovalStatus::APPROVED && $this->canAny($user, [
            'sales.credit_memo.post',
            'post:sales_credit_memo',
            'sales_credit_memo_post',
        ]);
    }
}
