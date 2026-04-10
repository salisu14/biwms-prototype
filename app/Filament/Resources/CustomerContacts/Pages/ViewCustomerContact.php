<?php

namespace App\Filament\Resources\CustomerContacts\Pages;

use App\Filament\Resources\CustomerContacts\CustomerContactResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerContact extends ViewRecord
{
    protected static string $resource = CustomerContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
