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

    public function submit(User $user, Payment $payment): bool
    {
        return $this->canAny($user, [
            'finance.payment.submit',
            'submit:payment',
            'payment_submit',
        ]);
    }

    public function approve(User $user, Payment $payment): bool
    {
        return $this->canAny($user, [
            'finance.payment.approve',
            'approve:payment',
            'payment_approve',
        ]);
    }

    public function reject(User $user, Payment $payment): bool
    {
        return $this->canAny($user, [
            'finance.payment.reject',
            'reject:payment',
            'payment_reject',
        ]);
    }

    public function reopen(User $user, Payment $payment): bool
    {
        return $this->canAny($user, [
            'finance.payment.reopen',
            'reopen:payment',
            'payment_reopen',
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

    public function unapply(User $user, Payment $payment): bool
    {
        return $this->canAny($user, [
            'finance.payment.unapply',
            'finance.payment.apply',
            'unapply:payment',
            'payment_unapply',
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

    public function reverse(User $user, Payment $payment): bool
    {
        return $this->void($user, $payment)
            || $this->canAny($user, [
                'finance.payment.reverse',
                'reverse:payment',
                'payment_reverse',
            ]);
    }

    public function cancel(User $user, Payment $payment): bool
    {
        return $this->canAny($user, [
            'finance.payment.cancel',
            'cancel:payment',
            'payment_cancel',
        ]);
    }
}
