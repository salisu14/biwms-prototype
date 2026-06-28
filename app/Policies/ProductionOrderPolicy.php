<?php

namespace App\Policies;

use App\Models\Manufacturing\ProductionOrder;
use App\Models\User;

class ProductionOrderPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'factory.production_order';
    }

    protected function legacyKey(): string
    {
        return 'production_order';
    }

    public function postOutput(User $user, ProductionOrder $productionOrder): bool
    {
        return $this->canAny($user, [
            'factory.production_order.post_output',
            'factory.production_order.post',
            'post:production_order',
            'production_order_post',
        ]);
    }

    public function finish(User $user, ProductionOrder $productionOrder): bool
    {
        return $this->canAny($user, [
            'factory.production_order.finish',
            'factory.production_order.post',
            'finish:production_order',
            'production_order_finish',
        ]);
    }

    public function post(User $user, ProductionOrder $productionOrder): bool
    {
        return $this->postOutput($user, $productionOrder) || $this->finish($user, $productionOrder);
    }

    public function submit(User $user, ProductionOrder $productionOrder): bool
    {
        return $this->canAny($user, [
            'factory.production_order.submit',
            'submit:production_order',
            'production_order_submit',
        ]);
    }

    public function approve(User $user, ProductionOrder $productionOrder): bool
    {
        return $this->canAny($user, [
            'factory.production_order.approve',
            'approve:production_order',
            'production_order_approve',
        ]);
    }

    public function reject(User $user, ProductionOrder $productionOrder): bool
    {
        return $this->canAny($user, [
            'factory.production_order.reject',
            'reject:production_order',
            'production_order_reject',
        ]);
    }

    public function reopen(User $user, ProductionOrder $productionOrder): bool
    {
        return $this->canAny($user, [
            'factory.production_order.reopen',
            'reopen:production_order',
            'production_order_reopen',
        ]);
    }

    public function reverse(User $user, ProductionOrder $productionOrder): bool
    {
        return $this->canAny($user, [
            'factory.production_order.reverse',
            'reverse:production_order',
            'production_order_reverse',
        ]);
    }

    public function cancel(User $user, ProductionOrder $productionOrder): bool
    {
        return $this->canAny($user, [
            'factory.production_order.cancel',
            'cancel:production_order',
            'production_order_cancel',
        ]);
    }
}
