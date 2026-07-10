<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeePayslipHistories\Pages;

use App\Filament\Resources\EmployeePayslipHistories\EmployeePayslipHistoryResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployeePayslipHistories extends ListRecords
{
    protected static string $resource = EmployeePayslipHistoryResource::class;
}
