<?php

namespace App\Filament\Resources\EmployeePayCodes\Pages;

use App\Filament\Resources\EmployeePayCodes\EmployeePayCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeePayCode extends EditRecord
{
    protected static string $resource = EmployeePayCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
