<?php

namespace App\Filament\Resources\GeneralBusinessPostingGroups\Pages;

use App\Filament\Resources\GeneralBusinessPostingGroups\GeneralBusinessPostingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeneralBusinessPostingGroup extends EditRecord
{
    protected static string $resource = GeneralBusinessPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
