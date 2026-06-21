<?php

namespace App\Policies;

class OverheadCostCategoryPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'factory.overhead_cost_category';
    }

    protected function legacyKey(): string
    {
        return 'overhead_cost_category';
    }
}
