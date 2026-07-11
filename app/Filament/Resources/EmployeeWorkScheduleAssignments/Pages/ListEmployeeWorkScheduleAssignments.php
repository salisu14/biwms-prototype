<?php

namespace App\Filament\Resources\EmployeeWorkScheduleAssignments\Pages;

use App\Filament\Resources\EmployeeWorkScheduleAssignments\EmployeeWorkScheduleAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeWorkScheduleAssignments extends ListRecords
{
    protected static string $resource = EmployeeWorkScheduleAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
