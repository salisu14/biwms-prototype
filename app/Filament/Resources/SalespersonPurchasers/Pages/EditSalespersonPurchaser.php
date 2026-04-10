<?php

namespace App\Filament\Resources\SalespersonPurchasers\Pages;

use App\Filament\Resources\SalespersonPurchaserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditSalespersonPurchaser extends EditRecord
{
    protected static string $resource = SalespersonPurchaserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
