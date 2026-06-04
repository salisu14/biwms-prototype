<?php

namespace App\Filament\Resources\ItemCharges\Pages;

use App\Filament\Resources\ItemCharges\ItemChargeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemCharges extends ListRecords
{
    protected static string $resource = ItemChargeResource::class;

    protected static ?string $title = 'Item Charges';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
