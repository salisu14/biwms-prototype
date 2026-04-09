<?php

namespace App\Filament\Resources\PostedPurchaseCreditMemos\Pages;

use App\Filament\Resources\PostedPurchaseCreditMemos\PostedPurchaseCreditMemoResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPostedPurchaseCreditMemo extends ViewRecord
{
    protected static string $resource = PostedPurchaseCreditMemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
