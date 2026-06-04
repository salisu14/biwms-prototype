<?php

namespace App\Filament\Resources\ItemCategoryAssignments\Pages;

use App\Filament\Resources\ItemCategoryAssignments\ItemCategoryAssignmentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemCategoryAssignment extends ViewRecord
{
    protected static string $resource = ItemCategoryAssignmentResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';
        $categoryCode = $record->category?->category_code ?? 'Category';

        return "{$itemCode} • Scope ".($record->is_primary ? 'Primary' : 'Secondary').' • Attribute '.$categoryCode;
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

        return "{$itemCode} - {$categoryCode}";
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
