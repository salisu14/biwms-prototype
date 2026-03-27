<?php

namespace App\Filament\Resources\DocumentHeaders\Pages;

use App\Filament\Resources\DocumentHeaders\DocumentHeaderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDocumentHeader extends EditRecord
{
    protected static string $resource = DocumentHeaderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
