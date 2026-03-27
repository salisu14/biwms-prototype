<?php

namespace App\Filament\Resources\ItemLedgers\Pages;

use App\Filament\Resources\ItemLedgers\ItemLedgerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemLedger extends ViewRecord
{
    protected static string $resource = ItemLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
