<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\OpeningInventory;
use App\Models\User;

class OpeningInventoryPolicy extends BaseFilamentPolicy
{
    protected string $module = 'inventory';

    protected string $resource = 'opening_inventory';

    public function view(User $user, mixed $model): bool
    {
        return $model instanceof OpeningInventory
            && $this->can($user, 'view')
            && $this->withinActiveBusiness($model);
    }

    public function update(User $user, mixed $model): bool
    {
        return $model instanceof OpeningInventory
            && $model->status === OpeningInventory::STATUS_DRAFT
            && $this->can($user, 'update')
            && $this->withinActiveBusiness($model);
    }

    public function delete(User $user, mixed $model): bool
    {
        return $model instanceof OpeningInventory
            && $model->status === OpeningInventory::STATUS_DRAFT
            && $this->can($user, 'delete')
            && $this->withinActiveBusiness($model);
    }

    public function post(User $user, OpeningInventory $openingInventory): bool
    {
        return $openingInventory->status === OpeningInventory::STATUS_DRAFT
            && $this->can($user, 'post')
            && $this->withinActiveBusiness($openingInventory);
    }

    public function cancel(User $user, OpeningInventory $openingInventory): bool
    {
        return $openingInventory->status === OpeningInventory::STATUS_DRAFT
            && $this->can($user, 'cancel')
            && $this->withinActiveBusiness($openingInventory);
    }

    private function withinActiveBusiness(OpeningInventory $openingInventory): bool
    {
        $activeBusinessId = (int) session('active_business_id', 0);

        return $activeBusinessId === 0
            || $openingInventory->business_id === null
            || (int) $openingInventory->business_id === $activeBusinessId;
    }
}
