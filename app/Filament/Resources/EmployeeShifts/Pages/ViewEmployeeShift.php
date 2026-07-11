<?php

namespace App\Filament\Resources\EmployeeShifts\Pages;

use App\Filament\Resources\EmployeeShifts\EmployeeShiftResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeShift extends ViewRecord
{
    protected static string $resource = EmployeeShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
