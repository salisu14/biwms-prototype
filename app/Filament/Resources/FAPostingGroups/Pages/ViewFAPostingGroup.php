<?php

namespace App\Filament\Resources\FAPostingGroups\Pages;

use App\Filament\Resources\FAPostingGroups\FAPostingGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFAPostingGroup extends ViewRecord
{
    protected static string $resource = FAPostingGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
