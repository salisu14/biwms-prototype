<?php

namespace App\Filament\Resources\ProductionBomVersions\Pages;

use App\Filament\Resources\ProductionBomVersions\ProductionBomVersionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionBomVersion extends EditRecord
{
    protected static string $resource = ProductionBomVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
