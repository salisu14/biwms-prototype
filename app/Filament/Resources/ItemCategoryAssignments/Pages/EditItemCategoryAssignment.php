<?php

namespace App\Filament\Resources\ItemCategoryAssignments\Pages;

use App\Filament\Resources\ItemCategoryAssignments\ItemCategoryAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItemCategoryAssignment extends EditRecord
{
    protected static string $resource = ItemCategoryAssignmentResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';
        $categoryCode = $record->category?->category_code ?? 'Category';

        return "Edit {$itemCode} • {$categoryCode}";
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();
        $itemDescription = $record->item?->description ?? 'Item';

        return "{$itemDescription} • ".($record->is_primary ? 'Primary' : 'Secondary');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';
        $categoryCode = $record->category?->category_code ?? 'Category';

        return "{$itemCode} • {$categoryCode}";
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
