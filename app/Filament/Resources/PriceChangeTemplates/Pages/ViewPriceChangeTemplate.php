<?php

namespace App\Filament\Resources\PriceChangeTemplates\Pages;

use App\Filament\Resources\PriceChangeTemplates\PriceChangeTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPriceChangeTemplate extends ViewRecord
{
    protected static string $resource = PriceChangeTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
