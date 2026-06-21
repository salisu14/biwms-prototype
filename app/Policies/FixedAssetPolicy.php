<?php

namespace App\Policies;

use App\Models\FixedAsset;
use App\Models\User;

class FixedAssetPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'fixed_asset';
    }

    protected function legacyKey(): string
    {
        return 'fixed_asset';
    }

    public function acquire(User $user, FixedAsset $fixedAsset): bool
    {
        return $this->canAny($user, [
            'fixed_asset.acquire',
            'fa.acquire',
            'acquire:fixed_asset',
            'fixed_asset_acquire',
        ]);
    }

    public function depreciate(User $user, FixedAsset $fixedAsset): bool
    {
        return $this->canAny($user, [
            'fixed_asset.depreciate',
            'fa.depreciate',
            'depreciate:fixed_asset',
            'fixed_asset_depreciate',
        ]);
    }

    public function dispose(User $user, FixedAsset $fixedAsset): bool
    {
        return $this->canAny($user, [
            'fixed_asset.dispose',
            'fa.dispose',
            'dispose:fixed_asset',
            'fixed_asset_dispose',
        ]);
    }
}
