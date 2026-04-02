<?php

namespace App\Filament\Resources\VatMasters\Pages;

use App\Filament\Resources\VatMasters\VatMasterResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVatMaster extends ViewRecord
{
    protected static string $resource = VatMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
