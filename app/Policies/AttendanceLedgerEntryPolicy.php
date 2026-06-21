<?php

namespace App\Policies;

class AttendanceLedgerEntryPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'hr.attendance';
    }

    protected function legacyKey(): string
    {
        return 'attendance';
    }
}
