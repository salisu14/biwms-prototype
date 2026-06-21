<?php

namespace App\Filament\Resources\CapExProjects\Pages;

use App\Filament\Resources\CapExProjects\CapExProjectResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCapExProjects extends ListRecords
{
    protected static string $resource = CapExProjectResource::class;

    protected static ?string $title = 'CapEx Projects';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-s-plus')
                ->label('Create CapEx Project'),
        ];
    }
}
