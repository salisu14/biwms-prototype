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
}
