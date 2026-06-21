<?php

namespace App\Filament\Resources\TaxTable\Pages;

use App\Filament\Resources\TaxTable\TaxTableResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTaxTables extends ListRecords
{
    protected static string $resource = TaxTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
