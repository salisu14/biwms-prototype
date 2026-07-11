<?php

namespace App\Filament\Resources\AttendanceDevices\Pages;

use App\Filament\Resources\AttendanceDevices\AttendanceDeviceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceDevices extends ListRecords
{
    protected static string $resource = AttendanceDeviceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
