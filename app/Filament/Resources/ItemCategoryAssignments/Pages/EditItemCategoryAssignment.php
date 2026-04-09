<?php

namespace App\Filament\Resources\ItemCategoryAssignments\Pages;

use App\Filament\Resources\ItemCategoryAssignments\ItemCategoryAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItemCategoryAssignment extends EditRecord
{
    protected static string $resource = ItemCategoryAssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
