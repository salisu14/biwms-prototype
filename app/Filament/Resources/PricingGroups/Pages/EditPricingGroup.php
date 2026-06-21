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
        return 'pricing group • '.($this->record->code ?? '—')
            .' • '.($this->record->pricing_strategy ?? '—');
    }

    public function getSubheading(): string
    {
        return 'currency • '.($this->record->currency_code ?? '—')
            .' • status • '.($this->record->blocked ? 'Blocked' : 'Active');
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
