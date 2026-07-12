<?php

declare(strict_types=1);

namespace App\Filament\Resources\AttendanceLedgerEntries\Pages;

use App\Filament\Resources\AttendanceLedgerEntries\AttendanceLedgerEntryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAttendanceLedgerEntry extends ViewRecord
{
    protected static string $resource = AttendanceLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
