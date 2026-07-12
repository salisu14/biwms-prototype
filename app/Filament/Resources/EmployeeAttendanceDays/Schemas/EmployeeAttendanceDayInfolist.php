<?php

namespace App\Filament\Resources\EmployeeAttendanceDays\Schemas;

use App\Models\EmployeeAttendanceDay;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class EmployeeAttendanceDayInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, EmployeeAttendanceDay::class);
    }
}
