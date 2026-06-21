<?php

namespace App\Filament\Resources\ValueEntries\Pages;

use App\Filament\Resources\ValueEntries\ValueEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditValueEntry extends EditRecord
{
    protected static string $resource = ValueEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
