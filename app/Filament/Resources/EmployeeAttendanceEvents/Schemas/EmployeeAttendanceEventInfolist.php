<?php

namespace App\Filament\Resources\EmployeeAttendanceEvents\Schemas;

use App\Models\EmployeeAttendanceEvent;
use App\Support\Filament\AttendanceReviewResourceSchema;
use Filament\Schemas\Schema;

class EmployeeAttendanceEventInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return AttendanceReviewResourceSchema::infolist($schema, EmployeeAttendanceEvent::class);
    }
}
