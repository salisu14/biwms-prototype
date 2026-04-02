<?php

namespace App\Filament\Resources\WorkCenterCalendars\Pages;

use App\Filament\Resources\WorkCenterCalendars\WorkCenterCalendarResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkCenterCalendar extends ViewRecord
{
    protected static string $resource = WorkCenterCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
