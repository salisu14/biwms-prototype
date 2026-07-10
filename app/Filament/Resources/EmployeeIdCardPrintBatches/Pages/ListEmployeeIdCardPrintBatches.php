<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCardPrintBatches\Pages;

use App\Filament\Resources\EmployeeIdCardPrintBatches\EmployeeIdCardPrintBatchResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeIdCardPrintBatches extends ListRecords
{
    protected static string $resource = EmployeeIdCardPrintBatchResource::class;
}
