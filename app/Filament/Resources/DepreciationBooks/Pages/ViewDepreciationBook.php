<?php

namespace App\Filament\Resources\DepreciationBooks\Pages;

use App\Filament\Resources\DepreciationBooks\DepreciationBookResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDepreciationBook extends ViewRecord
{
    protected static string $resource = DepreciationBookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
