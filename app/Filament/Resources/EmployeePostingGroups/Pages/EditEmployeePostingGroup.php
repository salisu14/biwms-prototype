<?php

namespace App\Filament\Resources\EmployeePostingGroups\Pages;

use App\Filament\Resources\EmployeePostingGroups\EmployeePostingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEmployeePostingGroup extends EditRecord
{
    protected static string $resource = EmployeePostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
