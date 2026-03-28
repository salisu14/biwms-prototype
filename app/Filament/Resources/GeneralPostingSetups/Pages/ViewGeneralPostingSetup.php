<?php

namespace App\Filament\Resources\GeneralPostingSetups\Pages;

use App\Filament\Resources\GeneralPostingSetups\GeneralPostingSetupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGeneralPostingSetup extends ViewRecord
{
    protected static string $resource = GeneralPostingSetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
