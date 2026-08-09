<?php

declare(strict_types=1);

namespace App\Filament\Resources\Manufacturing\ProductionCampaigns\Pages;

use App\Filament\Resources\Manufacturing\ProductionCampaigns\ProductionCampaignResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionCampaign extends EditRecord
{
    protected static string $resource = ProductionCampaignResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
