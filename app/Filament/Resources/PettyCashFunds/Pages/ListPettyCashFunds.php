<?php

namespace App\Filament\Resources\PettyCashFunds\Pages;

use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPettyCashFunds extends ListRecords
{
    protected static string $resource = PettyCashFundResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
