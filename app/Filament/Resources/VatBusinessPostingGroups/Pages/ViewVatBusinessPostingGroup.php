<?php

namespace App\Filament\Resources\VatBusinessPostingGroups\Pages;

use App\Filament\Resources\VatBusinessPostingGroups\VatBusinessPostingGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVatBusinessPostingGroup extends ViewRecord
{
    protected static string $resource = VatBusinessPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
