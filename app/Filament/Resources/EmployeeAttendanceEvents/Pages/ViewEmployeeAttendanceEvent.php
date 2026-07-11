<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeAttendanceEvents\Pages;

use App\Filament\Resources\EmployeeAttendanceEvents\EmployeeAttendanceEventResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeAttendanceEvent extends ViewRecord
{
    protected static string $resource = EmployeeAttendanceEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
