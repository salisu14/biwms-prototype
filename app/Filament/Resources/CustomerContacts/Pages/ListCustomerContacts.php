<?php

namespace App\Filament\Resources\CustomerContacts\Pages;

use App\Filament\Resources\CustomerContacts\CustomerContactResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerContacts extends ListRecords
{
    protected static string $resource = CustomerContactResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
