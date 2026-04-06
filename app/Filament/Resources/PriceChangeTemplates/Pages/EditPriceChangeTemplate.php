<?php

namespace App\Filament\Resources\PriceChangeTemplates\Pages;

use App\Filament\Resources\PriceChangeTemplates\PriceChangeTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPriceChangeTemplate extends EditRecord
{
    protected static string $resource = PriceChangeTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
