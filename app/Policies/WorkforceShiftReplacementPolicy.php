<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WorkforceShiftReplacement;

class WorkforceShiftReplacementPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.shift_replacement';
    }

    protected function legacyKey(): string
    {
        return 'shift_replacement';
    }

    public function approve(User $user, WorkforceShiftReplacement $record): bool
    {
        return $record->status === WorkforceShiftReplacement::STATUS_PROPOSED && $user->can('hr.shift_replacement.approve');
    }
}
