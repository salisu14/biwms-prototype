<?php

namespace App\Filament\Resources\GeneralPostingSetups\Pages;

use App\Filament\Resources\GeneralPostingSetups\GeneralPostingSetupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditGeneralPostingSetup extends EditRecord
{
    protected static string $resource = GeneralPostingSetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
