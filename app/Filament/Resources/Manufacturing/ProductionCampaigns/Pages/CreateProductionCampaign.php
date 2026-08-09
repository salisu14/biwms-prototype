<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionCampaigns\Pages;

use App\Filament\Resources\Manufacturing\ProductionCampaigns\ProductionCampaignResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionCampaign extends CreateRecord
{
    protected static string $resource = ProductionCampaignResource::class;
}
