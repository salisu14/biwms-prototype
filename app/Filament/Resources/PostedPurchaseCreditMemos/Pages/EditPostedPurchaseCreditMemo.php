<?php

namespace App\Filament\Resources\PostedPurchaseCreditMemos\Pages;

use App\Filament\Resources\PostedPurchaseCreditMemos\PostedPurchaseCreditMemoResource;
use Filament\Resources\Pages\EditRecord;

class EditPostedPurchaseCreditMemo extends EditRecord
{
    protected static string $resource = PostedPurchaseCreditMemoResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
