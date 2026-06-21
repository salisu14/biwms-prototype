<?php

namespace App\Filament\Resources\VatMasters\Pages;

use App\Filament\Resources\VatMasters\VatMasterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVatMasters extends ListRecords
{
    protected static string $resource = VatMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
