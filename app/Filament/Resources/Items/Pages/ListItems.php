<?php

namespace App\Filament\Resources\Items\Pages;

use App\Filament\Resources\Items\ItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItems extends ListRecords
{
    protected static string $resource = ItemResource::class;

    protected static ?string $title = 'Items';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
