<?php

namespace App\Filament\Resources\AttendanceLocations\Pages;

use App\Filament\Resources\AttendanceLocations\AttendanceLocationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceLocation extends CreateRecord
{
    protected static string $resource = AttendanceLocationResource::class;
}
