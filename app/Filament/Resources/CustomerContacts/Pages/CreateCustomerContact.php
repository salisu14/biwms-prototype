<?php

namespace App\Filament\Resources\CustomerContacts\Pages;

use App\Enums\ContactRole;
use App\Filament\Resources\CustomerContacts\CustomerContactResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCustomerContact extends CreateRecord
{
    protected static string $resource = CustomerContactResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Default to CUSTOMER if creating from here
        if (! isset($data['role'])) {
            $data['role'] = ContactRole::CUSTOMER;
        }

        return $data;
    }
}
