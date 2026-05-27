<?php

namespace App\Policies;

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
}
