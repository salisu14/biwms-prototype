<?php

namespace App\Filament\Resources\UnitOfMeasures\Pages;

use App\Filament\Resources\UnitOfMeasures\UnitOfMeasureResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUnitOfMeasure extends ViewRecord
{
    protected static string $resource = UnitOfMeasureResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
