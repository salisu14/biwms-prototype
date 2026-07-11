<?php

namespace App\Filament\Resources\AttendanceDevices\Pages;

use App\Filament\Resources\AttendanceDevices\AttendanceDeviceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceDevice extends EditRecord
{
    protected static string $resource = AttendanceDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
