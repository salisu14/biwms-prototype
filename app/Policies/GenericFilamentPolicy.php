<?php

namespace App\Policies;

use App\Models\User;
use App\Support\FilamentPermissionRegistry;

class GenericFilamentPolicy extends BaseFilamentPolicy
{
    protected function can(User $user, string $action, ?string $modelClass = null): bool
    {
        $parts = app(FilamentPermissionRegistry::class)->permissionPartsForModel($modelClass ?? '');

        if ($parts === null) {
            return false;
        }

        return $user->can("{$parts['module']}.{$parts['resource']}.{$action}");
    }

    public function viewAny(User $user): bool
    {
        return $this->can($user, 'view_any', $this->modelClassFromCurrentRoute());
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->can($user, 'view', $model::class)
            || $this->can($user, 'view', $this->modelClassFromCurrentRoute());
    }

    public function create(User $user): bool
    {
        return $this->can($user, 'create', $this->modelClassFromCurrentRoute());
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->can($user, 'update', $model::class)
            || $this->can($user, 'update', $this->modelClassFromCurrentRoute());
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->can($user, 'delete', $model::class)
            || $this->can($user, 'delete', $this->modelClassFromCurrentRoute());
    }

    public function deleteAny(User $user): bool
    {
        return $this->can($user, 'delete_any', $this->modelClassFromCurrentRoute());
    }

    public function restore(User $user, mixed $model): bool
    {
        return $this->can($user, 'restore', $model::class)
            || $this->can($user, 'restore', $this->modelClassFromCurrentRoute());
    }

    public function restoreAny(User $user): bool
    {
        return $this->can($user, 'restore_any', $this->modelClassFromCurrentRoute());
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return $this->can($user, 'force_delete', $model::class)
            || $this->can($user, 'force_delete', $this->modelClassFromCurrentRoute());
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->can($user, 'force_delete_any', $this->modelClassFromCurrentRoute());
    }

    private function modelClassFromCurrentRoute(): ?string
    {
        $routeName = (string) request()->route()?->getName();
        $registry = app(FilamentPermissionRegistry::class);

        foreach ($registry->resources() as $resourceClass) {
            if (! method_exists($resourceClass, 'getRouteBaseName') || ! method_exists($resourceClass, 'getModel')) {
                continue;
            }

            if (str_starts_with($routeName, $resourceClass::getRouteBaseName())) {
                return $resourceClass::getModel();
            }
        }

        return request()->route()?->parameter('record')?->getMorphClass();
    }
}
