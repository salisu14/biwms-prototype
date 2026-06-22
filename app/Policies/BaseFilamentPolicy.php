<?php

namespace App\Policies;

use App\Models\User;

abstract class BaseFilamentPolicy
{
    protected string $module;
    protected string $resource;

    protected function can(User $user, string $action): bool
    {
        return $user->can("{$this->module}.{$this->resource}.{$action}");
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'view_any');
    }

    public function view(User $user): bool
    {
        return $this->can($user, 'view');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create');
    }

    public function update(User $user): bool
    {
        return $this->can($user, 'update');
    }

    public function delete(User $user): bool
    {
        return $this->can($user, 'delete');
    }
}
