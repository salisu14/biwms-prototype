<?php

namespace App\Filament\Resources\ItemSkus\Pages;

use App\Filament\Resources\ItemSkus\ItemSkuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemSkus extends ListRecords
{
    protected static string $resource = ItemSkuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
