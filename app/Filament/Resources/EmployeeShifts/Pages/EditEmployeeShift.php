<?php

namespace App\Filament\Resources\EmployeeShifts\Pages;

use App\Filament\Resources\EmployeeShifts\EmployeeShiftResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeeShift extends EditRecord
{
    protected static string $resource = EmployeeShiftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
