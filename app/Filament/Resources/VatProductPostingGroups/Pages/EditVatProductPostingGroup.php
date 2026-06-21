<?php

namespace App\Filament\Resources\VatProductPostingGroups\Pages;

use App\Filament\Resources\VatProductPostingGroups\VatProductPostingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditVatProductPostingGroup extends EditRecord
{
    protected static string $resource = VatProductPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
