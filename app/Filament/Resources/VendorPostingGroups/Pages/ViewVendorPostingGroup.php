<?php

namespace App\Filament\Resources\VendorPostingGroups\Pages;

use App\Filament\Resources\VendorPostingGroups\VendorPostingGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorPostingGroup extends ViewRecord
{
    protected static string $resource = VendorPostingGroupResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->code ?? 'Vendor Posting Group')
            .' • '.($record->description ?? 'Description')
            .' • '.($record->blocked ? 'Blocked' : 'Active');
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return trim(implode(' • ', array_filter([
            $record->payablesAccount?->name,
            $record->paymentDiscDebitAccount?->name,
            $record->paymentDiscCreditAccount?->name,
        ]))) ?: 'Vendor posting group settings';
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->code ?? 'Vendor Posting Group';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
