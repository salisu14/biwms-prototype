<?php

namespace App\Filament\Resources\ProductionBoms\Pages;

use App\Filament\Resources\ProductionBoms\ProductionBomResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionBom extends ViewRecord
{
    protected static string $resource = ProductionBomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
