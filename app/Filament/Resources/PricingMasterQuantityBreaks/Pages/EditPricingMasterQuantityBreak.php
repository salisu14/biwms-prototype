<?php

namespace App\Filament\Resources\PricingMasterQuantityBreaks\Pages;

use App\Filament\Resources\PricingMasterQuantityBreaks\PricingMasterQuantityBreakResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPricingMasterQuantityBreak extends EditRecord
{
    protected static string $resource = PricingMasterQuantityBreakResource::class;

    public function getHeading(): string
    {
        return 'Quantity Break Line '.($this->record->line_number ?? '—')
            .' • Scope '.($this->record->pricingMaster?->price_list_code ?? '—')
            .' • Attribute '.$this->record->getTierDescription($this->record->pricingMaster?->currency_code);
    }

    public function getSubheading(): string
    {
        return 'Line '.($this->record->line_number ?? '—')
            .' • Scope '.($this->record->pricingMaster?->description ?? '—')
            .' • Attribute '.($this->record->unit_of_measure_code ?? '—');
    }

    public function getBreadcrumb(): string
    {
        return 'Line '.($this->record->line_number ?? '—')
            .' - '.($this->record->pricingMaster?->price_list_code ?? '—');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
