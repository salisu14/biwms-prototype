<?php

namespace App\Filament\Resources\EmployeeAttendanceDays\Pages;

use App\Filament\Resources\EmployeeAttendanceDays\EmployeeAttendanceDayResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeAttendanceDay extends EditRecord
{
    protected static string $resource = EmployeeAttendanceDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
