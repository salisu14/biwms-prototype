<?php

namespace App\Filament\Resources\VatPostingSetups\Pages;

use App\Filament\Resources\VatPostingSetups\VatPostingSetupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVatPostingSetups extends ListRecords
{
    protected static string $resource = VatPostingSetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
