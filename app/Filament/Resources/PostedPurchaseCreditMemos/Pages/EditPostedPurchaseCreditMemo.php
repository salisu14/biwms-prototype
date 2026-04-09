<?php

namespace App\Filament\Resources\PostedPurchaseCreditMemos\Pages;

use App\Filament\Resources\PostedPurchaseCreditMemos\PostedPurchaseCreditMemoResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPostedPurchaseCreditMemo extends EditRecord
{
    protected static string $resource = PostedPurchaseCreditMemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
