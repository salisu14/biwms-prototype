<?php

namespace App\Filament\Resources\EmployeeAttendanceEvents\Schemas;

use App\Models\EmployeeAttendanceEvent;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class EmployeeAttendanceEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, EmployeeAttendanceEvent::class);
    }
}
