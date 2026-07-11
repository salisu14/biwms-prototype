<?php

namespace App\Filament\Resources\AttendanceDevices\Pages;

use App\Filament\Resources\AttendanceDevices\AttendanceDeviceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendanceDevice extends ViewRecord
{
    protected static string $resource = AttendanceDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
