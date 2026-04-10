<?php

namespace App\Filament\Resources\Dimensions\Pages;

use App\Filament\Resources\Dimensions\DimensionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDimension extends ViewRecord
{
    protected static string $resource = DimensionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
