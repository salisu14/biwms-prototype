<?php

namespace App\Filament\Resources\EmployeePayCodes\Pages;

use App\Filament\Resources\EmployeePayCodes\EmployeePayCodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeePayCodes extends ListRecords
{
    protected static string $resource = EmployeePayCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
