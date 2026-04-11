<?php

namespace App\Filament\Resources\VatBusinessPostingGroups\Pages;

use App\Filament\Resources\VatBusinessPostingGroups\VatBusinessPostingGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVatBusinessPostingGroups extends ListRecords
{
    protected static string $resource = VatBusinessPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
