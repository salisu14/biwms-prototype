<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'finance.payment';
    }

    protected function legacyKey(): string
    {
        return 'payment';
    }

    public function post(User $user, Payment $payment): bool
    {
        return $this->canAny($user, [
            'finance.payment.post',
            'post:payment',
            'payment_post',
        ]);
    }

    public function apply(User $user, Payment $payment): bool
    {
        return $this->canAny($user, [
            'finance.payment.apply',
            'apply:payment',
            'payment_apply',
        ]);
    }

    public function reconcile(User $user, Payment $payment): bool
    {
        return $this->canAny($user, [
            'finance.payment.reconcile',
            'reconcile:payment',
            'payment_reconcile',
        ]);
    }

    public function void(User $user, Payment $payment): bool
    {
        return $this->canAny($user, [
            'finance.payment.void',
            'void:payment',
            'payment_void',
        ]);
    }
}
