<?php

namespace App\Filament\Resources\GeneralProductPostingGroups\Pages;

use App\Filament\Resources\GeneralProductPostingGroups\GeneralProductPostingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeneralProductPostingGroup extends EditRecord
{
    protected static string $resource = GeneralProductPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
