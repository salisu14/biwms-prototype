<?php

namespace App\Policies;

use App\Models\SalesOrder;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class SalesOrderPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->canAny($user, [
            'sales.order.view_any',
            'view:any:sales_order',
            'view:any:order',
            'sales_order_access',
        ]);
    }

    public function view(User $user, SalesOrder $salesOrder): bool
    {
        return $this->canAny($user, [
            'sales.order.view',
            'view:sales_order',
            'show:sales_order',
            'sales_order_show',
            'view:any:order',
        ]);
    }

    public function create(User $user): bool
    {
        return $this->canAny($user, [
            'sales.order.create',
            'create:sales_order',
            'create:order',
            'sales_order_create',
        ]);
    }

    public function update(User $user, SalesOrder $salesOrder): bool
    {
        return $this->canAny($user, [
            'sales.order.update',
            'edit:sales_order',
            'edit:order',
            'sales_order_edit',
        ]);
    }

    public function delete(User $user, SalesOrder $salesOrder): bool
    {
        return $this->canAny($user, [
            'sales.order.delete',
            'delete:sales_order',
            'delete:order',
            'sales_order_delete',
        ]);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canAny($user, [
            'sales.order.delete_any',
            'sales.order.delete',
            'delete:sales_order',
            'delete:order',
            'sales_order_delete',
        ]);
    }

    public function approve(User $user, SalesOrder $salesOrder): bool
    {
        return $this->canAny($user, [
            'sales.order.approve',
            'approve:sales_order',
            'approve:order',
        ]);
    }

    public function post(User $user, SalesOrder $salesOrder): bool
    {
        return $this->canAny($user, [
            'sales.order.post',
            'post:sales_order',
            'post:order',
        ]);
    }

    public function restore(User $user, SalesOrder $salesOrder): bool
    {
        return $this->delete($user, $salesOrder);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDelete(User $user, SalesOrder $salesOrder): bool
    {
        return $this->delete($user, $salesOrder);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->deleteAny($user);
    }
}
