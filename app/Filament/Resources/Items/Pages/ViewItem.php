<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Items\ItemResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItem extends ViewRecord
{
    protected static string $resource = ItemResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $category = $record->primaryCategory?->category_name ?? 'Uncategorized';
        $itemType = $record->item_type?->label() ?? 'Unknown Type';

        return ($record->item_code ?? 'Item')
            .' • Scope '.$category
            .' • Attribute '.$itemType;
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();
        $location = $record->location?->code
            ? "{$record->location->code} - {$record->location->name}"
            : ($record->location?->name ?? 'No default location');

        return ($record->sku ?: 'No SKU')
            .' • '.$location
            .' • '.number_format((float) $record->unit_price, 2).' '.($record->currency?->code ?? '');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return ($record->item_code ?? 'Item').($record->description ? ' - '.$record->description : '');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
