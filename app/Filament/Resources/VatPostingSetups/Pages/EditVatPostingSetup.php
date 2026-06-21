<?php

namespace App\Filament\Resources\VatPostingSetups\Pages;

use App\Filament\Resources\VatPostingSetups\VatPostingSetupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVatPostingSetup extends EditRecord
{
    protected static string $resource = VatPostingSetupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
