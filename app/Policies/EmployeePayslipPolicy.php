<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\EmployeePayslip;
use App\Models\User;

class EmployeePayslipPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.employee_payslip';
    }

    protected function legacyKey(): string
    {
        return 'employee_payslip';
    }

    public function view(User $user, mixed $record): bool
    {
        return parent::view($user, $record) || $this->ownsPayslip($user, $record);
    }

    public function download(User $user, EmployeePayslip $payslip): bool
    {
        return $user->can('hr.employee_payslip.download') || $this->ownsPayslip($user, $payslip);
    }

    public function print(User $user, EmployeePayslip $payslip): bool
    {
        return $user->can('hr.employee_payslip.print') || $this->download($user, $payslip);
    }

    public function generate(User $user): bool
    {
        return $user->can('hr.employee_payslip.generate');
    }

    public function revoke(User $user, EmployeePayslip $payslip): bool
    {
        return $user->can('hr.employee_payslip.revoke');
    }

    public function regenerate(User $user, EmployeePayslip $payslip): bool
    {
        return $user->can('hr.employee_payslip.regenerate');
    }

    public function update(User $user, mixed $record): bool
    {
        return false;
    }

    public function delete(User $user, mixed $record): bool
    {
        return false;
    }

    private function ownsPayslip(User $user, mixed $record): bool
    {
        return $record instanceof EmployeePayslip
            && $user->employee_id !== null
            && (int) $user->employee_id === (int) $record->employee_id;
    }
}
