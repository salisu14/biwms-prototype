<?php

declare(strict_types=1);

namespace App\Policies;

class EmployeeIdCardPrintBatchPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.employee_id_card_print_batch';
    }

    protected function legacyKey(): string
    {
        return 'employee_id_card_print_batch';
    }
}
