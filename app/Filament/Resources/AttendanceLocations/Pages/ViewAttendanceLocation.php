<?php

namespace App\Filament\Resources\AttendanceLocations\Pages;

use App\Filament\Resources\AttendanceLocations\AttendanceLocationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendanceLocation extends ViewRecord
{
    protected static string $resource = AttendanceLocationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
