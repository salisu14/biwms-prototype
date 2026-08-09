<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionCampaigns\Pages;

use App\Filament\Resources\Manufacturing\ProductionCampaigns\ProductionCampaignResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionCampaigns extends ListRecords
{
    protected static string $resource = ProductionCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
