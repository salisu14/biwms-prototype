<?php

namespace App\Filament\Resources\PriceChangeTemplates\Pages;

use App\Filament\Resources\PriceChangeTemplates\PriceChangeTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPriceChangeTemplates extends ListRecords
{
    protected static string $resource = PriceChangeTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
