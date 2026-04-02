<?php

namespace App\Filament\Resources\BlanketOrders\Pages;

use App\Filament\Resources\BlanketOrders\BlanketOrderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBlanketOrders extends ListRecords
{
    protected static string $resource = BlanketOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
