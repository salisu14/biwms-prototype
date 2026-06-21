<?php

namespace App\Policies;

class ActualOverheadCostPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'factory.actual_overhead_cost';
    }

    protected function legacyKey(): string
    {
        return 'actual_overhead_cost';
    }
}
