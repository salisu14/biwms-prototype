<?php

namespace App\Filament\Resources\CustomerContacts\Pages;

use App\Filament\Resources\CustomerContacts\CustomerContactResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditCustomerContact extends EditRecord
{
    protected static string $resource = CustomerContactResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->full_name ?? $record->name ?? $record->company_name ?? 'Customer Contact')
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
        ]))) ?: 'Edit customer contact details';
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->full_name ?? $record->name ?? $record->company_name ?? 'Customer Contact';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
