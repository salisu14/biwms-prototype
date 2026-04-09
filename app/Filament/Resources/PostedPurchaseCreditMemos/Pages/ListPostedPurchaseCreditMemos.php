<?php

namespace App\Filament\Resources\PostedPurchaseCreditMemos\Pages;

use App\Filament\Resources\PostedPurchaseCreditMemos\PostedPurchaseCreditMemoResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPostedPurchaseCreditMemos extends ListRecords
{
    protected static string $resource = PostedPurchaseCreditMemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
