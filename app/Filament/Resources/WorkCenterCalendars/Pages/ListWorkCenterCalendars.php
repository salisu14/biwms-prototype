<?php

namespace App\Filament\Resources\WorkCenterCalendars\Pages;

use App\Filament\Resources\WorkCenterCalendars\WorkCenterCalendarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkCenterCalendars extends ListRecords
{
    protected static string $resource = WorkCenterCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
