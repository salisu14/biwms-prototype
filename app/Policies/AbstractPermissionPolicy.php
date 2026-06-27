<?php

namespace App\Policies;

use App\Models\User;
use App\Policies\Concerns\ChecksPermissions;

abstract class AbstractPermissionPolicy
{
    use ChecksPermissions;

    abstract protected function permissionPrefix(): string;

    abstract protected function legacyKey(): string;

    public function viewAny(User $user): bool
    {
        return $this->canAny($user, [
            $this->permissionPrefix().'.view_any',
            'view:any:'.$this->legacyKey(),
            $this->legacyKey().'_access',
        ]);
    }

    public function view(User $user, mixed $record): bool
    {
        return $this->canAny($user, [
            $this->permissionPrefix().'.view',
            'view:'.$this->legacyKey(),
            $this->legacyKey().'_show',
            $this->legacyKey().'_access',
        ]);
    }

    public function create(User $user): bool
    {
        return $this->canAny($user, [
            $this->permissionPrefix().'.create',
            'create:'.$this->legacyKey(),
            $this->legacyKey().'_create',
        ]);
    }

    public function update(User $user, mixed $record): bool
    {
        return $this->canAny($user, [
            $this->permissionPrefix().'.update',
            'edit:'.$this->legacyKey(),
            'update:'.$this->legacyKey(),
            $this->legacyKey().'_edit',
        ]);
    }

    public function delete(User $user, mixed $record): bool
    {
        return $this->canAny($user, [
            $this->permissionPrefix().'.delete',
            'delete:'.$this->legacyKey(),
            $this->legacyKey().'_delete',
        ]);
    }

    public function deleteAny(User $user): bool
    {
        return $this->canAny($user, [
            $this->permissionPrefix().'.delete_any',
            $this->permissionPrefix().'.delete',
            'delete:'.$this->legacyKey(),
            $this->legacyKey().'_delete',
        ]);
    }

    public function restore(User $user, mixed $record): bool
    {
        return $this->canAny($user, [
            $this->permissionPrefix().'.restore',
            $this->permissionPrefix().'.delete',
            'delete:'.$this->legacyKey(),
            $this->legacyKey().'_delete',
        ]);
    }

    public function restoreAny(User $user): bool
    {
        return $this->canAny($user, [
            $this->permissionPrefix().'.restore_any',
            $this->permissionPrefix().'.restore',
            $this->permissionPrefix().'.delete',
            'delete:'.$this->legacyKey(),
            $this->legacyKey().'_delete',
        ]);
    }

    public function forceDelete(User $user, mixed $record): bool
    {
        return $this->canAny($user, [
            $this->permissionPrefix().'.force_delete',
            $this->permissionPrefix().'.delete',
            'delete:'.$this->legacyKey(),
            $this->legacyKey().'_delete',
        ]);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->canAny($user, [
            $this->permissionPrefix().'.force_delete_any',
            $this->permissionPrefix().'.force_delete',
            $this->permissionPrefix().'.delete',
            'delete:'.$this->legacyKey(),
            $this->legacyKey().'_delete',
        ]);
    }
}
