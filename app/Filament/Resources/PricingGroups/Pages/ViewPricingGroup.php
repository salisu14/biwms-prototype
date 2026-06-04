<?php

namespace App\Filament\Resources\PricingGroups\Pages;

use App\Filament\Resources\PricingGroups\PricingGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPricingGroup extends ViewRecord
{
    protected static string $resource = PricingGroupResource::class;

    public function getHeading(): string
    {
        return 'Pricing Group Code '.($this->record->code ?? '—')
            .' • Scope '.($this->record->name ?? '—')
            .' • Attribute '.($this->record->blocked ? 'Blocked' : 'Active');
    }

    public function getSubheading(): string
    {
        return 'Code '.($this->record->code ?? '—')
            .' • Scope '.($this->record->name ?? '—')
            .' • Attribute '.($this->record->blocked ? 'Blocked' : 'Active');
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
