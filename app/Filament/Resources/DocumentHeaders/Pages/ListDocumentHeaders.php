<?php

namespace App\Filament\Resources\DocumentHeaders\Pages;

use App\Filament\Resources\DocumentHeaders\DocumentHeaderResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDocumentHeaders extends ListRecords
{
    protected static string $resource = DocumentHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
