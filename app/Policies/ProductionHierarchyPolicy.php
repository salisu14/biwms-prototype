<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\User;

class ProductionHierarchyPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'manufacturing.production_hierarchy';
    }

    protected function legacyKey(): string
    {
        return 'production_hierarchy';
    }

    public function update(User $user, mixed $record): bool
    {
        return parent::update($user, $record)
            && (! $record instanceof ProductionHierarchy || ! $record->status?->isImmutable());
    }

    public function delete(User $user, mixed $record): bool
    {
        return parent::delete($user, $record)
            && (! $record instanceof ProductionHierarchy || ! $record->status?->isImmutable());
    }

    public function explode(User $user, ProductionHierarchy $hierarchy): bool
    {
        return $user->can('manufacturing.production_hierarchy.explode') && ! $hierarchy->status?->isImmutable();
    }

    public function generateChildren(User $user, ProductionHierarchy $hierarchy): bool
    {
        return $user->can('manufacturing.production_hierarchy.generate_children') && ! $hierarchy->status?->isImmutable();
    }

    public function release(User $user, ProductionHierarchy $hierarchy): bool
    {
        return $user->can('manufacturing.production_hierarchy.release');
    }

    public function replan(User $user, ProductionHierarchy $hierarchy): bool
    {
        return $user->can('manufacturing.production_hierarchy.replan') && ! $hierarchy->status?->isImmutable();
    }

    public function cancel(User $user, ProductionHierarchy $hierarchy): bool
    {
        return $user->can('manufacturing.production_hierarchy.cancel');
    }
}
