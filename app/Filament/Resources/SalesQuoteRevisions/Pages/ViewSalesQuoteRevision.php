<?php

namespace App\Filament\Resources\SalesQuoteRevisions\Pages;

use App\Filament\Resources\SalesQuoteRevisions\SalesQuoteRevisionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesQuoteRevision extends ViewRecord
{
    protected static string $resource = SalesQuoteRevisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
