<?php

namespace App\Filament\Resources\FAClasses\Pages;

use App\Filament\Resources\FAClasses\FAClassResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFAClasses extends ListRecords
{
    protected static string $resource = FAClassResource::class;

    protected static ?string $title = 'FA Classes';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon('heroicon-s-plus')
                ->label('Create FA Class'),
        ];
    }
}
