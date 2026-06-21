<?php

namespace App\Filament\Resources\DepreciationBooks\Pages;

use App\Filament\Resources\DepreciationBooks\DepreciationBookResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditDepreciationBook extends EditRecord
{
    protected static string $resource = DepreciationBookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
