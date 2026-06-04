<?php

namespace App\Filament\Resources\VendorContacts\Pages;

use App\Filament\Resources\VendorContacts\VendorContactResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorContact extends ViewRecord
{
    protected static string $resource = VendorContactResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->full_name ?? $record->name ?? $record->company_name ?? 'Vendor Contact')
            .' • '.($record->type?->label() ?? 'Contact')
            .' • '.($record->role?->label() ?? 'Role');
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return trim(implode(' • ', array_filter([
            $record->company_name,
            $record->email,
            $record->phone,
        ]))) ?: 'Vendor contact details';
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->full_name ?? $record->name ?? $record->company_name ?? 'Vendor Contact';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
