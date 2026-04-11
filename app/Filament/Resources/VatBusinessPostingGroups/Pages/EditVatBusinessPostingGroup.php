<?php

namespace App\Filament\Resources\VatBusinessPostingGroups\Pages;

use App\Filament\Resources\VatBusinessPostingGroups\VatBusinessPostingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVatBusinessPostingGroup extends EditRecord
{
    protected static string $resource = VatBusinessPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
