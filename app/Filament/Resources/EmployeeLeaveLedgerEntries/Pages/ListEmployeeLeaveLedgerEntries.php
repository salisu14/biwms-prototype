<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeLeaveLedgerEntries\Pages;

use App\Filament\Resources\EmployeeLeaveLedgerEntries\EmployeeLeaveLedgerEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeLeaveLedgerEntries extends ListRecords
{
    protected static string $resource = EmployeeLeaveLedgerEntryResource::class;
}
