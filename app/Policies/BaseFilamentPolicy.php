<?php

namespace App\Policies;

use App\Models\User;

abstract class BaseFilamentPolicy
{
    protected string $module;

    protected string $resource;

    protected function can(User $user, string $action): bool
    {
        if (property_exists($this, 'permissionPrefix') && ! isset($this->module, $this->resource)) {
            return $user->can($this->permissionPrefix.'.'.$action);
        }

        return $user->can("{$this->module}.{$this->resource}.{$action}");
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'view_any');
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->can($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create');
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->can($user, 'update');
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->can($user, 'delete');
    }

    public function deleteAny(User $user): bool
    {
        return $this->can($user, 'delete_any');
    }

    public function restore(User $user, mixed $model): bool
    {
        return $this->can($user, 'restore');
    }

    public function restoreAny(User $user): bool
    {
        return $this->can($user, 'restore_any');
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return $this->can($user, 'force_delete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->can($user, 'force_delete_any');
    }
}
