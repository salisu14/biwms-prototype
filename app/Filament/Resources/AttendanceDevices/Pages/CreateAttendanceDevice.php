<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceDevices\Pages;

use App\Filament\Resources\AttendanceDevices\AttendanceDeviceResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceDevice extends CreateRecord
{
    protected static string $resource = AttendanceDeviceResource::class;
}
