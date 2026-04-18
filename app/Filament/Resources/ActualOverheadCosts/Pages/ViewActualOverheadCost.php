<?php

namespace App\Filament\Resources\ActualOverheadCosts\Pages;

use App\Filament\Resources\ActualOverheadCosts\ActualOverheadCostResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewActualOverheadCost extends ViewRecord
{
    protected static string $resource = ActualOverheadCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
