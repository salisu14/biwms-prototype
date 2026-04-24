<?php

namespace App\Filament\Resources\GeneralProductPostingGroups\Pages;

use App\Filament\Resources\GeneralProductPostingGroups\GeneralProductPostingGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeneralProductPostingGroups extends ListRecords
{
    protected static string $resource = GeneralProductPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
