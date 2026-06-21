<?php

namespace App\Filament\Resources\ItemLots\Pages;

use App\Filament\Resources\ItemLots\ItemLotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemLots extends ListRecords
{
    protected static string $resource = ItemLotResource::class;

    protected static ?string $title = 'Item Lots';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
