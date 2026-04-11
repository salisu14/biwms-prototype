<?php

namespace App\Filament\Resources\FAPostingGroups\Pages;

use App\Filament\Resources\FAPostingGroups\FAPostingGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFAPostingGroup extends EditRecord
{
    protected static string $resource = FAPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
