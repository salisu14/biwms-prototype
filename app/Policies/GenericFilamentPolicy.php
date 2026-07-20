<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Support\FilamentPermissionRegistry;

class GenericFilamentPolicy extends BaseFilamentPolicy
{
    protected function can(
        User $user,
        string $action,
        ?string $modelClass = null
    ): bool {
        if ($modelClass === null || $modelClass === '') {
            return false;
        }

        $parts = app(FilamentPermissionRegistry::class)
            ->permissionPartsForModel($modelClass);

        if ($parts === null) {
            return false;
        }

        return $user->can(
            "{$parts['module']}.{$parts['resource']}.{$action}"
        );
    }

    public function viewAny(User $user): bool
    {
        return $this->can(
            $user,
            'view_any',
            $this->modelClassFromCurrentRoute()
        );
    }

    public function view(User $user, mixed $model): bool
    {
        return $this->canModelOrOwnerResource(
            $user,
            'view',
            $model::class
        );
    }

    public function create(User $user): bool
    {
        return $this->can(
            $user,
            'create',
            $this->modelClassFromCurrentRoute()
        );
    }

    public function update(User $user, mixed $model): bool
    {
        return $this->canModelOrOwnerResource(
            $user,
            'update',
            $model::class
        );
    }

    public function delete(User $user, mixed $model): bool
    {
        return $this->canModelOrOwnerResource(
            $user,
            'delete',
            $model::class
        );
    }

    public function deleteAny(User $user): bool
    {
        return $this->can(
            $user,
            'delete_any',
            $this->modelClassFromCurrentRoute()
        );
    }

    public function reorder(User $user): bool
    {
        return $this->can(
            $user,
            'reorder',
            $this->modelClassFromCurrentRoute()
        );
    }

    public function restore(User $user, mixed $model): bool
    {
        return $this->canModelOrOwnerResource(
            $user,
            'restore',
            $model::class
        );
    }

    public function restoreAny(User $user): bool
    {
        return $this->can(
            $user,
            'restore_any',
            $this->modelClassFromCurrentRoute()
        );
    }

    public function forceDelete(User $user, mixed $model): bool
    {
        return $this->canModelOrOwnerResource(
            $user,
            'force_delete',
            $model::class
        );
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->can(
            $user,
            'force_delete_any',
            $this->modelClassFromCurrentRoute()
        );
    }

    public function submit(User $user, mixed $model): bool
    {
        return $this->canModelOrOwnerResource(
            $user,
            'submit',
            $model::class
        );
    }

    public function approve(User $user, mixed $model): bool
    {
        return $this->canModelOrOwnerResource(
            $user,
            'approve',
            $model::class
        );
    }

    public function reject(User $user, mixed $model): bool
    {
        return $this->canModelOrOwnerResource(
            $user,
            'reject',
            $model::class
        );
    }

    public function reopen(User $user, mixed $model): bool
    {
        return $this->canModelOrOwnerResource(
            $user,
            'reopen',
            $model::class
        );
    }

    public function post(User $user, mixed $model): bool
    {
        return $this->canModelOrOwnerResource(
            $user,
            'post',
            $model::class
        );
    }

    public function reverse(User $user, mixed $model): bool
    {
        return $this->canModelOrOwnerResource(
            $user,
            'reverse',
            $model::class
        );
    }

    public function cancel(User $user, mixed $model): bool
    {
        return $this->canModelOrOwnerResource(
            $user,
            'cancel',
            $model::class
        );
    }

    private function modelClassFromCurrentRoute(): ?string
    {
        $route = request()->route();
        $routeName = (string) $route?->getName();
        $registry = app(FilamentPermissionRegistry::class);

        foreach ($registry->resources() as $resourceClass) {
            if (
                ! method_exists($resourceClass, 'getRouteBaseName')
                || ! method_exists($resourceClass, 'getModel')
            ) {
                continue;
            }

            if (
                str_starts_with(
                    $routeName,
                    $resourceClass::getRouteBaseName()
                )
            ) {
                return $resourceClass::getModel();
            }
        }

        $record = $route?->parameter('record');

        return is_object($record)
        && method_exists($record, 'getMorphClass')
            ? $record->getMorphClass()
            : null;
    }

    private function canModelOrOwnerResource(
        User $user,
        string $action,
        string $modelClass
    ): bool {
        $registry = app(FilamentPermissionRegistry::class);

        if (
            $registry->permissionPartsForModel($modelClass) !== null
        ) {
            return $this->can(
                $user,
                $action,
                $modelClass
            );
        }

        return $this->can(
            $user,
            $action,
            $this->modelClassFromCurrentRoute()
        );
    }
}
