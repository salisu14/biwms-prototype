<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy extends BaseFilamentPolicy
{
    protected string $module = 'admin';

    protected string $resource = 'user';

    public function create(User $actor): bool
    {
        return $this->can($actor, 'create');
    }

    public function update(User $actor, mixed $target): bool
    {
        if ($target instanceof User) {
            if ($target->hasRole('super_admin') && ! $actor->hasRole('super_admin')) {
                return false;
            }
        }

        return $this->can($actor, 'update');
    }

    public function delete(User $actor, mixed $target): bool
    {
        if ($target instanceof User && $actor->is($target)) {
            return false;
        }

        return $this->can($actor, 'delete');
    }

    public function deleteAny(User $actor): bool
    {
        return $this->can($actor, 'delete_any');
    }
}
