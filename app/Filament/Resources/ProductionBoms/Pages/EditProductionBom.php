<?php

namespace App\Filament\Resources\ProductionBoms\Pages;

use App\Filament\Resources\ProductionBoms\ProductionBomResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionBom extends EditRecord
{
    protected static string $resource = ProductionBomResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
