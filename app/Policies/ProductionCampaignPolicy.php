<?php

declare(strict_types=1);

namespace App\Policies;

class ProductionCampaignPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'manufacturing.production_campaign';
    }

    protected function legacyKey(): string
    {
        return 'production_campaign';
    }
}
