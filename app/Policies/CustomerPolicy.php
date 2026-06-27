<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class CustomerPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->canAny($user, [
            'sales.customer.view_any',
            'view:any:customer',
            'customer_access',
        ]);
    }

    public function view(User $user, Customer $customer): bool
    {
        return $this->canAny($user, [
            'sales.customer.view',
            'view:customer',
            'customer_show',
            'customer_access',
        ]);
    }

    public function create(User $user): bool
    {
        return $this->canAny($user, [
            'sales.customer.create',
            'create:customer',
            'customer_create',
        ]);
    }

    public function update(User $user, Customer $customer): bool
    {
        return $this->canAny($user, [
            'sales.customer.update',
            'edit:customer',
            'customer_edit',
        ]);
    }

    public function delete(User $user, Customer $customer): bool
    {
        return $this->canAny($user, [
            'sales.customer.delete',
            'delete:customer',
            'customer_delete',
        ]);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canAny($user, [
            'sales.customer.delete_any',
            'sales.customer.delete',
            'delete:customer',
            'customer_delete',
        ]);
    }

    public function restore(User $user, Customer $customer): bool
    {
        return $this->delete($user, $customer);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDelete(User $user, Customer $customer): bool
    {
        return $this->delete($user, $customer);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->deleteAny($user);
    }
}
