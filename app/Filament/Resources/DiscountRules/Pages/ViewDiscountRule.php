<?php

namespace App\Filament\Resources\DiscountRules\Pages;

use App\Filament\Resources\DiscountRules\DiscountRuleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDiscountRule extends ViewRecord
{
    protected static string $resource = DiscountRuleResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->item?->item_code ?? 'Discount Rule')
            .' • Scope '.($record->customerGroup?->code ?? 'All Groups')
            .' • Attribute '.number_format((float) $record->discount_percent, 2).'%';
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->item?->description ?? 'Unknown Item')
            .' • '.($record->customerGroup?->name ?? 'All Groups')
            .' • '.($record->start_date?->format('d/m/Y') ?? 'Immediate').' - '.($record->end_date?->format('d/m/Y') ?? 'Open');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->item
            ? "{$record->item->item_code} - ".($record->customerGroup?->code ?? 'All Groups')
            : 'Discount Rule';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
