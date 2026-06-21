<?php

namespace App\Filament\Resources\SalesQuotes\Pages;

use App\Filament\Resources\SalesQuotes\SalesQuoteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesQuote extends CreateRecord
{
    protected static string $resource = SalesQuoteResource::class;
}
