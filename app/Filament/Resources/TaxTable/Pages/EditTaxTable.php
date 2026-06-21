<?php

namespace App\Filament\Resources\TaxTable\Pages;

use App\Filament\Resources\TaxTable\TaxTableResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTaxTable extends EditRecord
{
    protected static string $resource = TaxTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
