<?php

namespace App\Filament\Resources\VatMasters\Pages;

use App\Filament\Resources\VatMasters\VatMasterResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVatMaster extends EditRecord
{
    protected static string $resource = VatMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
