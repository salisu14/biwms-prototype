<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class ProductionIntermediateHandoffPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'manufacturing.production_intermediate_handoff';
    }

    protected function legacyKey(): string
    {
        return 'production_intermediate_handoff';
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, mixed $record): bool
    {
        return false;
    }

    public function delete(User $user, mixed $record): bool
    {
        return false;
    }
}
