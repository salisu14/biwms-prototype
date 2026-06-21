<?php

namespace App\Filament\Resources\ItemUomAssignments\Pages;

use App\Filament\Resources\ItemUomAssignments\ItemUomAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemUomAssignments extends ListRecords
{
    protected static string $resource = ItemUomAssignmentResource::class;

    protected static ?string $title = 'Item UOM Assignments';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
