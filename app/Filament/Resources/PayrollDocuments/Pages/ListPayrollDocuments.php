<?php

declare(strict_types=1);

namespace App\Filament\Resources\PayrollDocuments\Pages;

use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPayrollDocuments extends ListRecords
{
    protected static string $resource = PayrollDocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
