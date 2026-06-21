<?php

namespace App\Filament\Resources\PurchaseQuotes\Pages;

use App\Filament\Resources\PurchaseQuotes\PurchaseQuoteResource;
use App\Filament\Traits\ShowsMissingNumberSeriesWarning;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchaseQuote extends CreateRecord
{
    use ShowsMissingNumberSeriesWarning;

    protected static string $resource = PurchaseQuoteResource::class;

    public function mount(): void
    {
        parent::mount();

        $this->warnIfMissingNumberSeries(['P-QUOTE', 'PURCHASE_QUOTE', 'PQ'], 'Purchase Quote');
    }
}
