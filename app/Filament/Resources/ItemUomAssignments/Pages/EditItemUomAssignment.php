<?php

namespace App\Filament\Resources\ItemUomAssignments\Pages;

use App\Filament\Resources\ItemUomAssignments\ItemUomAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItemUomAssignment extends EditRecord
{
    protected static string $resource = ItemUomAssignmentResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';
        $uomCode = $record->uom?->uom_code ?? 'UoM';

        return $record->item?->item_code ? "Edit {$itemCode} • {$uomCode}" : 'Edit Item UOM Assignment';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();
        $itemDescription = $record->item?->description ?? 'Item';

        return "{$itemDescription} • {$record->uom_type_label}";
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';
        $uomCode = $record->uom?->uom_code ?? 'UoM';

        return $record->item?->item_code ? "{$itemCode} • {$uomCode}" : 'Item UOM Assignment';
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
