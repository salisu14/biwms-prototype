<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

class EmployeePayslipHistoryPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.employee_payslip_history';
    }

    protected function legacyKey(): string
    {
        return 'employee_payslip_history';
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
