<?php

namespace App\Filament\Resources\AttendanceLedgerEntries\Pages;

use App\Filament\Resources\AttendanceLedgerEntries\AttendanceLedgerEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditAttendanceLedgerEntry extends EditRecord
{
    protected static string $resource = AttendanceLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
