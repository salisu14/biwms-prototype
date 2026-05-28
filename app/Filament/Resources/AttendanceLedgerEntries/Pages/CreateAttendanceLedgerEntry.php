<?php

namespace App\Filament\Resources\AttendanceLedgerEntries\Pages;

use App\Filament\Resources\AttendanceLedgerEntries\AttendanceLedgerEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAttendanceLedgerEntry extends CreateRecord
{
    protected static string $resource = AttendanceLedgerEntryResource::class;
}
