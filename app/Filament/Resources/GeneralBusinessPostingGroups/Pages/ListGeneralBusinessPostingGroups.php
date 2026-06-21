<?php

namespace App\Filament\Resources\GeneralBusinessPostingGroups\Pages;

use App\Filament\Resources\GeneralBusinessPostingGroups\GeneralBusinessPostingGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeneralBusinessPostingGroups extends ListRecords
{
    protected static string $resource = GeneralBusinessPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
