<?php

namespace App\Filament\Resources\ItemUomAssignments\Pages;

use App\Filament\Resources\ItemUomAssignments\ItemUomAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItemUomAssignment extends EditRecord
{
    protected static string $resource = ItemUomAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
