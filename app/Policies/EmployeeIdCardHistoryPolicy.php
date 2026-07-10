<?php

declare(strict_types=1);

namespace App\Policies;

class EmployeeIdCardHistoryPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.employee_id_card_history';
    }

    protected function legacyKey(): string
    {
        return 'employee_id_card_history';
    }
}
