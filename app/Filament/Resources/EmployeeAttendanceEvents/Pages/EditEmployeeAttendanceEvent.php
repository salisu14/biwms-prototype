<?php

namespace App\Filament\Resources\EmployeeAttendanceEvents\Pages;

use App\Filament\Resources\EmployeeAttendanceEvents\EmployeeAttendanceEventResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeAttendanceEvent extends EditRecord
{
    protected static string $resource = EmployeeAttendanceEventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
