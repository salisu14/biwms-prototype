<?php

namespace App\Filament\Resources\WorkCenterGroups\Pages;

use App\Filament\Resources\WorkCenterGroups\WorkCenterGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkCenterGroups extends ListRecords
{
    protected static string $resource = WorkCenterGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
