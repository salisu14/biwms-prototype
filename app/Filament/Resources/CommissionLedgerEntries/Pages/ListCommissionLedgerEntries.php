<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionLedgerEntries\Pages;

use App\Filament\Resources\CommissionLedgerEntries\CommissionLedgerEntryResource;
use Filament\Resources\Pages\ListRecords;

class ListCommissionLedgerEntries extends ListRecords
{
    protected static string $resource = CommissionLedgerEntryResource::class;
}
