<?php

namespace App\Filament\Resources\CustomerPostingGroups\Pages;

use App\Filament\Resources\CustomerPostingGroups\CustomerPostingGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCustomerPostingGroups extends ListRecords
{
    protected static string $resource = CustomerPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
