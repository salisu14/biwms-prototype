<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Manufacturing\ProductionOrderSupplyLink;
use App\Models\User;

class ProductionOrderSupplyLinkPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'manufacturing.production_supply_link';
    }

    protected function legacyKey(): string
    {
        return 'production_supply_link';
    }

    public function update(User $user, mixed $record): bool
    {
        return $user->can('manufacturing.production_supply_link.update_planned')
            && (! $record instanceof ProductionOrderSupplyLink || $record->status?->value === 'planned');
    }

    public function updatePlanned(User $user, ProductionOrderSupplyLink $link): bool
    {
        return $this->update($user, $link);
    }

    public function cancel(User $user, ProductionOrderSupplyLink $link): bool
    {
        return $user->can('manufacturing.production_supply_link.cancel');
    }
}
