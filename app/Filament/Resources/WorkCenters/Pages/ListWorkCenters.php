<?php

namespace App\Filament\Resources\WorkCenters\Pages;

use App\Filament\Resources\WorkCenters\WorkCenterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkCenters extends ListRecords
{
    protected static string $resource = WorkCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
