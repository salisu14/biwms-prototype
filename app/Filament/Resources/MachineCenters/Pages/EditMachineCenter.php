<?php

namespace App\Filament\Resources\MachineCenters\Pages;

use App\Filament\Resources\MachineCenters\MachineCenterResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditMachineCenter extends EditRecord
{
    protected static string $resource = MachineCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
