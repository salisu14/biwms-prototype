<?php

namespace App\Filament\Resources\SalespersonPurchasers\Pages;

use App\Filament\Resources\SalespersonPurchasers\SalespersonPurchaserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListSalespersonPurchasers extends ListRecords
{
    protected static string $resource = SalespersonPurchaserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
