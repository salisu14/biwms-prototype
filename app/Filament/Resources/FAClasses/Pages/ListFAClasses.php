<?php

namespace App\Filament\Resources\FAClasses\Pages;

use App\Filament\Resources\FAClasses\FAClassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFAClasses extends ListRecords
{
    protected static string $resource = FAClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
