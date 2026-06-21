<?php

namespace App\Filament\Resources\VendorContacts\Pages;

use App\Enums\ContactRole;
use App\Filament\Resources\VendorContacts\VendorContactResource;
use Filament\Resources\Pages\CreateRecord;

class CreateVendorContact extends CreateRecord
{
    protected static string $resource = VendorContactResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Default to VENDOR if creating from here
        if (! isset($data['role'])) {
            $data['role'] = ContactRole::VENDOR;
        }

        return $data;
    }
}
