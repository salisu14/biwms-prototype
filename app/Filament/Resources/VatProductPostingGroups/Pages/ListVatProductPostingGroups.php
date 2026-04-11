<?php

namespace App\Filament\Resources\VatProductPostingGroups\Pages;

use App\Filament\Resources\VatProductPostingGroups\VatProductPostingGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVatProductPostingGroups extends ListRecords
{
    protected static string $resource = VatProductPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
