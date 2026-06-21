<?php

namespace App\Filament\Resources\WorkCenterCalendars\Pages;

use App\Filament\Resources\WorkCenterCalendars\WorkCenterCalendarResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkCenterCalendar extends EditRecord
{
    protected static string $resource = WorkCenterCalendarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
