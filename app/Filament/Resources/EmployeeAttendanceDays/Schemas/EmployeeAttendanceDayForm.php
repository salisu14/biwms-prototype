<?php

namespace App\Filament\Resources\EmployeeAttendanceDays\Schemas;

use App\Models\EmployeeAttendanceDay;
use App\Support\Filament\AttendanceReviewResourceSchema;
use Filament\Schemas\Schema;

class EmployeeAttendanceDayForm
{
    public static function configure(Schema $schema): Schema
    {
        return AttendanceReviewResourceSchema::form($schema, EmployeeAttendanceDay::class);
    }
}
