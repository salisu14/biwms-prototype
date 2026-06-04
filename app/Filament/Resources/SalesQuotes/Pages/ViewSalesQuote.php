<?php

namespace App\Filament\Resources\SalesQuotes\Pages;

use App\Filament\Resources\SalesQuotes\SalesQuoteResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesQuote extends ViewRecord
{
    protected static string $resource = SalesQuoteResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $customer = $record->customer?->name ?? 'Unknown Customer';

        return ($record->quote_no ?? 'Sales Quote')
            .' • Scope '.$customer
            .' • Attribute '.($record->status?->label() ?? $record->status?->value ?? 'Unknown Status');
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->quote_date?->format('d/m/Y') ?? 'No quote date')
            .' • Expires '.($record->valid_until?->format('d/m/Y') ?? 'No expiry')
            .' • '.number_format((float) $record->total_amount, 2).' '.($record->currency_code ?: '');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();
        $customer = $record->customer?->name ?? 'Unknown Customer';

        return ($record->quote_no ?? 'Sales Quote').' - '.$customer;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
