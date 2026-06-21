<?php

namespace App\Policies;

use App\Models\PayrollDocument;
use App\Models\User;

class PayrollDocumentPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.payroll_document';
    }

    protected function legacyKey(): string
    {
        return 'payroll_document';
    }

    public function calculate(User $user, PayrollDocument $payrollDocument): bool
    {
        return $this->canAny($user, [
            'payroll.calculate',
            'hr.payroll_document.calculate',
            'calculate:payroll_document',
            'payroll_document_calculate',
        ]);
    }

    public function post(User $user, PayrollDocument $payrollDocument): bool
    {
        return $this->canAny($user, [
            'payroll.post',
            'hr.payroll_document.post',
            'post:payroll_document',
            'payroll_document_post',
        ]);
    }

    public function pay(User $user, PayrollDocument $payrollDocument): bool
    {
        return $this->canAny($user, [
            'payroll.pay',
            'hr.payroll_document.pay',
            'pay:payroll_document',
            'payroll_document_pay',
        ]);
    }
}
