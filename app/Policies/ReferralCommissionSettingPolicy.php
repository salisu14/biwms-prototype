<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class ReferralCommissionSettingPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'sales.referral_commission_setting';
    }

    protected function legacyKey(): string
    {
        return 'referral_commission_setting';
    }

    public function manage(User $user): bool
    {
        return $this->canAny($user, [
            $this->permissionPrefix().'.manage',
            $this->permissionPrefix().'.update',
        ]);
    }
}
