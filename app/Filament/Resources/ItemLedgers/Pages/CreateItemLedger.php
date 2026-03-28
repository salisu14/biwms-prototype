<?php

namespace App\Filament\Resources\ItemLedgers\Pages;

use App\Filament\Resources\ItemLedgers\ItemLedgerResource;
use Filament\Resources\Pages\CreateRecord;

class CreateItemLedger extends CreateRecord
{
    protected static string $resource = ItemLedgerResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();

        return $data;
    }
}
