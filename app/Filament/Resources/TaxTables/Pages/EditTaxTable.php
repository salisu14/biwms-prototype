<?php

namespace App\Filament\Resources\TaxTables\Pages;

use App\Filament\Resources\TaxTables\TaxTableResource;
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
