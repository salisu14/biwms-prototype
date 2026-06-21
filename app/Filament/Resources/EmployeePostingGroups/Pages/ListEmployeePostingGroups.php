<?php

namespace App\Filament\Resources\EmployeePostingGroups\Pages;

use App\Filament\Resources\EmployeePostingGroups\EmployeePostingGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeePostingGroups extends ListRecords
{
    protected static string $resource = EmployeePostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
