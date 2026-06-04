<?php

namespace App\Filament\Resources\PricingGroups\Pages;

use App\Filament\Resources\PricingGroups\PricingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPricingGroup extends EditRecord
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

    public function getBreadcrumb(): string
    {
        return ($this->record->code ?? '—')
            .' - '.($this->record->name ?? '—');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
