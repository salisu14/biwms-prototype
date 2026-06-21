<?php

namespace App\Filament\Resources\ItemCategoryAssignments\Pages;

use App\Filament\Resources\ItemCategoryAssignments\ItemCategoryAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemCategoryAssignments extends ListRecords
{
    protected static string $resource = ItemCategoryAssignmentResource::class;

    protected static ?string $title = 'Item Category Assignments';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
