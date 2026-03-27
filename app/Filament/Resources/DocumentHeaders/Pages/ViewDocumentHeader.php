<?php

namespace App\Filament\Resources\DocumentHeaders\Pages;

use App\Filament\Resources\DocumentHeaders\DocumentHeaderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDocumentHeader extends ViewRecord
{
    protected static string $resource = DocumentHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
