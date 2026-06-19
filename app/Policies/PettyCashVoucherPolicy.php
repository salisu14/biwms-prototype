<?php

namespace App\Policies;

use App\Models\PettyCashVoucher;
use App\Models\User;

class PettyCashVoucherPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'finance.petty_cash_voucher';
    }

    protected function legacyKey(): string
    {
        return 'petty_cash_voucher';
    }

    public function approve(User $user, PettyCashVoucher $voucher): bool
    {
        return $this->canAny($user, [
            'finance.petty_cash_voucher.approve',
            'approve:petty_cash_voucher',
            'petty_cash_voucher_approve',
        ]);
    }

    public function post(User $user, PettyCashVoucher $voucher): bool
    {
        return $this->canAny($user, [
            'finance.petty_cash_voucher.post',
            'post:petty_cash_voucher',
            'petty_cash_voucher_post',
        ]);
    }

    public function cancel(User $user, PettyCashVoucher $voucher): bool
    {
        return $this->canAny($user, [
            'finance.petty_cash_voucher.cancel',
            'cancel:petty_cash_voucher',
            'petty_cash_voucher_cancel',
        ]);
    }
}
