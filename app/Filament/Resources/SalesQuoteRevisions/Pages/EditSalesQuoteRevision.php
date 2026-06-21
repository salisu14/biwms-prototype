<?php

namespace App\Filament\Resources\SalesQuoteRevisions\Pages;

use App\Filament\Resources\SalesQuoteRevisions\SalesQuoteRevisionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesQuoteRevision extends EditRecord
{
    protected static string $resource = SalesQuoteRevisionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
