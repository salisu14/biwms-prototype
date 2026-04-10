<?php

namespace App\Filament\Resources\CustomerContacts\Pages;

use App\Filament\Resources\CustomerContacts\CustomerContactResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerContact extends EditRecord
{
    protected static string $resource = CustomerContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
