<?php

namespace App\Filament\Resources\WorkCenters\Pages;

use App\Filament\Resources\WorkCenters\WorkCenterResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWorkCenter extends EditRecord
{
    protected static string $resource = WorkCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
