<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class ProductionOperationDependencyPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'manufacturing.production_operation_dependency';
    }

    protected function legacyKey(): string
    {
        return 'production_operation_dependency';
    }

    public function create(User $user): bool
    {
        return $user->can('manufacturing.production_operation_dependency.generate');
    }

    public function update(User $user, mixed $record): bool
    {
        return false;
    }

    public function delete(User $user, mixed $record): bool
    {
        return false;
    }

    public function generate(User $user): bool
    {
        return $user->can('manufacturing.production_operation_dependency.generate');
    }

    public function reconcile(User $user): bool
    {
        return $user->can('manufacturing.production_operation_dependency.reconcile');
    }
}
