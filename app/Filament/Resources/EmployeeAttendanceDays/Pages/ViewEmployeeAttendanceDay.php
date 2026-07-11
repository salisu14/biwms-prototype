<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeAttendanceDays\Pages;

use App\Filament\Resources\EmployeeAttendanceDays\EmployeeAttendanceDayResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeAttendanceDay extends ViewRecord
{
    protected static string $resource = EmployeeAttendanceDayResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
