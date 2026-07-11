<?php

namespace App\Filament\Resources\EmployeeShifts\Pages;

use App\Filament\Resources\EmployeeShifts\EmployeeShiftResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeShifts extends ListRecords
{
    protected static string $resource = EmployeeShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
