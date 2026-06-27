<?php

namespace App\Policies;

use App\Models\User;

class RolePolicy extends BaseFilamentPolicy
{
    protected string $module = 'admin';

    protected string $resource = 'role';

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'view_any') || $user->can('role_permission.manage');
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->can($user, 'view') || $user->can('role_permission.manage');
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create') || $user->can('role_permission.manage');
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->can($user, 'update') || $user->can('role_permission.manage');
    }

    public function delete(User $user, mixed $model): bool
    {
        if ((string) $model->getAttribute('name') === 'super_admin') {
            return false;
        }

        return $this->can($user, 'delete') || $user->can('role_permission.manage');
    }
}
