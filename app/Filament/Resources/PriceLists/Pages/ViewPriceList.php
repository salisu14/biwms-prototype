<?php

namespace App\Filament\Resources\PriceLists\Pages;

use App\Filament\Resources\PriceLists\PriceListResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPriceList extends ViewRecord
{
    protected static string $resource = PriceListResource::class;

    public function getHeading(): string
    {
        $scope = $this->record->customer
            ? "{$this->record->customer->customer_number} - {$this->record->customer->name}"
            : ($this->record->customerGroup
                ? "{$this->record->customerGroup->code} - {$this->record->customerGroup->name}"
                : 'All Customers');

        return 'Price List Code '.($this->record->item?->item_code ?? 'Unknown Item')
            .' • Scope '.$scope
            .' • Attribute '.($this->record->currency ?? '—');
    }

    public function getSubheading(): string
    {
        return 'Code '.($this->record->item?->item_code ?? 'Unknown Item')
            .' • Scope '.(
                $this->record->customer
                    ? "{$this->record->customer->customer_number} - {$this->record->customer->name}"
                    : ($this->record->customerGroup
                        ? "{$this->record->customerGroup->code} - {$this->record->customerGroup->name}"
                        : 'All Customers')
            )
            .' • Attribute '.($this->record->currency ?? '—');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
