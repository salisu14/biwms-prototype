<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Manufacturing\ProductionMaterialReservation;
use App\Models\User;

class ProductionMaterialReservationPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'manufacturing.production_material_reservation';
    }

    protected function legacyKey(): string
    {
        return 'production_material_reservation';
    }

    public function update(User $user, mixed $record): bool
    {
        return false;
    }

    public function delete(User $user, mixed $record): bool
    {
        return false;
    }

    public function release(User $user, ProductionMaterialReservation $reservation): bool
    {
        return $user->can('manufacturing.production_material_reservation.release')
            && ! $reservation->status?->isImmutable();
    }
}
