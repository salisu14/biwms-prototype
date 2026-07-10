<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class EmployeeIdCardPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.employee_id_card';
    }

    protected function legacyKey(): string
    {
        return 'employee_id_card';
    }

    public function print(User $user): bool
    {
        return $user->can('hr.employee_id_card.print') || $user->can('hr.employee_id_card.download');
    }

    public function revoke(User $user): bool
    {
        return $user->can('hr.employee_id_card.revoke');
    }

    public function replace(User $user): bool
    {
        return $user->can('hr.employee_id_card.replace') || $user->can('hr.employee_id_card.regenerate');
    }
}
