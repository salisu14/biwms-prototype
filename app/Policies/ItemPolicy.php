<?php

namespace App\Policies;

use App\Models\Item;
use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

class ItemPolicy
{
    use ChecksPermissions;

    public function viewAny(User $user): bool
    {
        return $this->canAny($user, [
            'sales.item.view_any',
            'view:any:item',
            'item_access',
        ]);
    }

    public function view(User $user, Item $item): bool
    {
        return $this->canAny($user, [
            'sales.item.view',
            'view:item',
            'item_show',
            'item_access',
        ]);
    }

    public function create(User $user): bool
    {
        return $this->canAny($user, [
            'sales.item.create',
            'create:item',
            'item_create',
        ]);
    }

    public function update(User $user, Item $item): bool
    {
        return $this->canAny($user, [
            'sales.item.update',
            'edit:item',
            'item_edit',
        ]);
    }

    public function delete(User $user, Item $item): bool
    {
        return $this->canAny($user, [
            'sales.item.delete',
            'delete:item',
            'item_delete',
        ]);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canAny($user, [
            'sales.item.delete_any',
            'sales.item.delete',
            'delete:item',
            'item_delete',
        ]);
    }

    public function restore(User $user, Item $item): bool
    {
        return $this->delete($user, $item);
    }

    public function restoreAny(User $user): bool
    {
        return $this->deleteAny($user);
    }

    public function forceDelete(User $user, Item $item): bool
    {
        return $this->delete($user, $item);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->deleteAny($user);
    }
}
