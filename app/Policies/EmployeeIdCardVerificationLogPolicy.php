<?php

declare(strict_types=1);

namespace App\Policies;

class EmployeeIdCardVerificationLogPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.employee_id_card_verification_log';
    }

    protected function legacyKey(): string
    {
        return 'employee_id_card_verification_log';
    }
}
