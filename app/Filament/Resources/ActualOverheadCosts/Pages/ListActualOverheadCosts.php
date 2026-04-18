<?php

namespace App\Filament\Resources\ActualOverheadCosts\Pages;

use App\Filament\Resources\ActualOverheadCosts\ActualOverheadCostResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActualOverheadCosts extends ListRecords
{
    protected static string $resource = ActualOverheadCostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
