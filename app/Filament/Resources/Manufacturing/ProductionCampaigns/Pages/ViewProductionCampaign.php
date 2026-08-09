<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionCampaigns\Pages;

use App\Filament\Resources\Manufacturing\ProductionCampaigns\ProductionCampaignResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionCampaign extends ViewRecord
{
    protected static string $resource = ProductionCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
