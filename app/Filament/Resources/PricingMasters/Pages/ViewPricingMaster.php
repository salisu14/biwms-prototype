<?php

namespace App\Filament\Resources\PricingMasters\Pages;

use App\Filament\Resources\PricingMasters\PricingMasterResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPricingMaster extends ViewRecord
{
    protected static string $resource = PricingMasterResource::class;

    public function getHeading(): string
    {
        $scope = $this->record->customer
            ? "{$this->record->customer->customer_number} - {$this->record->customer->name}"
            : ($this->record->pricingGroup
                ? "{$this->record->pricingGroup->code} - {$this->record->pricingGroup->name}"
                : 'All Customers');

        return 'Pricing Master Code '.($this->record->price_list_code ?? '—')
            .' • Scope '.($this->record->item?->item_code ?? 'Unknown Item')
            .' • Attribute '.$scope;
    }

    public function getSubheading(): string
    {
        $scope = ($this->record->item?->item_code ?? 'Unknown Item')
            .' / '.(
                $this->record->customer
                    ? "{$this->record->customer->customer_number} - {$this->record->customer->name}"
                    : ($this->record->pricingGroup
                        ? "{$this->record->pricingGroup->code} - {$this->record->pricingGroup->name}"
                        : 'All Customers')
            );

        return 'Code '.($this->record->price_list_code ?? '—')
            .' • Scope '.$scope
            .' • Attribute '.($this->record->price_type ?? '—');
    }

    public function getBreadcrumb(): string
    {
        return ($this->record->price_list_code ?? '—')
            .' - '.($this->record->description ?? '—');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
