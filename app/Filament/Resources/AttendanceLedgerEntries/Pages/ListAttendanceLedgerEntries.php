<?php

namespace App\Filament\Resources\AttendanceLedgerEntries\Pages;

use App\Filament\Resources\AttendanceLedgerEntries\AttendanceLedgerEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceLedgerEntries extends ListRecords
{
    protected static string $resource = AttendanceLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
