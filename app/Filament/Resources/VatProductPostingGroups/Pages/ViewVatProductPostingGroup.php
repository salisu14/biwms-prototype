<?php

namespace App\Filament\Resources\VatProductPostingGroups\Pages;

use App\Filament\Resources\VatProductPostingGroups\VatProductPostingGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVatProductPostingGroup extends ViewRecord
{
    protected static string $resource = VatProductPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
