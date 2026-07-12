<?php

namespace App\Filament\Resources\EmployeeAttendanceEvents\Schemas;

use App\Models\EmployeeAttendanceEvent;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class EmployeeAttendanceEventForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, EmployeeAttendanceEvent::class);
    }
}
