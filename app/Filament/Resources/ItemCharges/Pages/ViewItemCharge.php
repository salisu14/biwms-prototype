<?php

namespace App\Filament\Resources\ItemCharges\Pages;

use App\Filament\Resources\ItemCharges\ItemChargeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemCharge extends ViewRecord
{
    protected static string $resource = ItemChargeResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return 'Charge No. '.($record->number ?? '—')
            .' • Scope '.($record->description ?? '—')
            .' • Attribute '.($record->gen_prod_posting_group ?? '—');
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->number ?? '—')
            .' • '.($record->getFullDescription() ?: 'No description');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->number ?: 'Item Charge';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
