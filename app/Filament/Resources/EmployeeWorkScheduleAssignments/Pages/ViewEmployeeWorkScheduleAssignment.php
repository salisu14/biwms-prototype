<?php

namespace App\Filament\Resources\EmployeeWorkScheduleAssignments\Pages;

use App\Filament\Resources\EmployeeWorkScheduleAssignments\EmployeeWorkScheduleAssignmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeWorkScheduleAssignment extends ViewRecord
{
    protected static string $resource = EmployeeWorkScheduleAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
