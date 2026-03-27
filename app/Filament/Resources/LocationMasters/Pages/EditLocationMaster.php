<?php

namespace App\Filament\Resources\LocationMasters\Pages;

use App\Filament\Resources\LocationMasters\LocationMasterResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditLocationMaster extends EditRecord
{
    protected static string $resource = LocationMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
