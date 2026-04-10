<?php

namespace App\Filament\Resources\PayrollDocuments\Pages;

use App\Filament\Resources\PayrollDocuments\PayrollDocumentResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayrollDocument extends CreateRecord
{
    protected static string $resource = PayrollDocumentResource::class;
}
