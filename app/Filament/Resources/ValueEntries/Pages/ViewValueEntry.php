<?php

namespace App\Filament\Resources\ValueEntries\Pages;

use App\Filament\Resources\ValueEntries\ValueEntryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewValueEntry extends ViewRecord
{
    protected static string $resource = ValueEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
//            EditAction::make(),
        ];
    }
}
