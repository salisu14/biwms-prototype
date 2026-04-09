<?php

namespace App\Filament\Resources\PurchaseQuotes\Pages;

use App\Filament\Resources\PurchaseQuotes\PurchaseQuoteResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseQuote extends EditRecord
{
    protected static string $resource = PurchaseQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
