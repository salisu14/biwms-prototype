<?php

namespace App\Filament\Resources\VendorPostingGroups\Pages;

use App\Filament\Resources\VendorPostingGroups\VendorPostingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVendorPostingGroup extends EditRecord
{
    protected static string $resource = VendorPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
