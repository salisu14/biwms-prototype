<?php

namespace App\Filament\Resources\EmployeeAttendanceEvents\Pages;

use App\Filament\Resources\EmployeeAttendanceEvents\EmployeeAttendanceEventResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployeeAttendanceEvent extends CreateRecord
{
    protected static string $resource = EmployeeAttendanceEventResource::class;
}
