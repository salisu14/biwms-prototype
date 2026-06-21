<?php

namespace App\Filament\Resources\PostedPurchaseCreditMemos\Pages;

use App\Filament\Resources\PostedPurchaseCreditMemos\PostedPurchaseCreditMemoResource;
use Filament\Resources\Pages\ListRecords;

class ListPostedPurchaseCreditMemos extends ListRecords
{
    protected static string $resource = PostedPurchaseCreditMemoResource::class;
}
