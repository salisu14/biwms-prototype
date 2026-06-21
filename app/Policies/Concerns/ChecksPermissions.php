<?php

namespace App\Policies\Concerns;

use App\Models\User;

trait ChecksPermissions
{
    /**
     * @param  array<int, string>  $permissions
     */
    protected function canAny(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($user->can($permission)) {
                return true;
            }
        }

        return false;
    }
}
