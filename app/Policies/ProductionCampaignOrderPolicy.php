<?php

declare(strict_types=1);

namespace App\Policies;

class ProductionCampaignOrderPolicy extends AbstractPermissionPolicy
{
    protected function permissionPrefix(): string
    {
        return 'manufacturing.production_campaign_order';
    }

    protected function legacyKey(): string
    {
        return 'production_campaign_order';
    }
}
