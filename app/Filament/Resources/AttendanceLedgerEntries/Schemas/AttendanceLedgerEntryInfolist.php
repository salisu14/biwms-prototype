<?php

namespace App\Filament\Resources\AttendanceLedgerEntries\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AttendanceLedgerEntryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('attendance_date')->date(),
                TextEntry::make('employee.employee_number')->label('Employee No.'),
                TextEntry::make('employee.full_name')->label('Employee'),
                TextEntry::make('clock_in_at')->dateTime(),
                TextEntry::make('clock_out_at')->dateTime(),
                TextEntry::make('break_minutes')->numeric(),
                TextEntry::make('worked_hours')->numeric(decimalPlaces: 2),
                TextEntry::make('status')->badge(),
                TextEntry::make('approver.name')->label('Approved By'),
                TextEntry::make('approved_at')->dateTime(),
                TextEntry::make('approval_note'),
            ]);
    }
}
