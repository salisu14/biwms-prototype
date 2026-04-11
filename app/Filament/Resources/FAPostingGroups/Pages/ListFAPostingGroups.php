<?php

namespace App\Filament\Resources\FAPostingGroups\Pages;

use App\Filament\Resources\FAPostingGroups\FAPostingGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFAPostingGroups extends ListRecords
{
    protected static string $resource = FAPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
