<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeIdCardHistories\Pages;

use App\Filament\Resources\EmployeeIdCardHistories\EmployeeIdCardHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeIdCardHistories extends ListRecords
{
    protected static string $resource = EmployeeIdCardHistoryResource::class;
}
