<?php

namespace App\Filament\Resources\FAClasses\Pages;

use App\Filament\Resources\FAClasses\FAClassResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFAClass extends EditRecord
{
    protected static string $resource = FAClassResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
