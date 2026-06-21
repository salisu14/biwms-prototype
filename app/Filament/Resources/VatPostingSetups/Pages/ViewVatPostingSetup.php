<?php

namespace App\Filament\Resources\VatPostingSetups\Pages;

use App\Filament\Resources\VatPostingSetups\VatPostingSetupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVatPostingSetup extends ViewRecord
{
    protected static string $resource = VatPostingSetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
