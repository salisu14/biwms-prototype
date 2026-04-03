<?php

namespace App\Filament\Resources\CapExProjects\Pages;

use App\Filament\Resources\CapExProjects\CapExProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCapExProjects extends ListRecords
{
    protected static string $resource = CapExProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
