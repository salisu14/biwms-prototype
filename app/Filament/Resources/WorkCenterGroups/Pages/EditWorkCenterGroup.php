<?php

namespace App\Filament\Resources\WorkCenterGroups\Pages;

use App\Filament\Resources\WorkCenterGroups\WorkCenterGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkCenterGroup extends EditRecord
{
    protected static string $resource = WorkCenterGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
