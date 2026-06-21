<?php

namespace App\Filament\Resources\ValueEntries\Pages;

use App\Filament\Resources\ValueEntries\ValueEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValueEntries extends ListRecords
{
    protected static string $resource = ValueEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
