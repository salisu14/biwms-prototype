<?php

namespace App\Filament\Resources\PayrollDocuments\Pages;

use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayrollDocument extends EditRecord
{
    protected static string $resource = PayrollDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
