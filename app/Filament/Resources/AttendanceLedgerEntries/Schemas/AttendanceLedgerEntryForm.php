<?php

namespace App\Filament\Resources\AttendanceLedgerEntries\Schemas;

use App\Models\Employee;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AttendanceLedgerEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('employee_id')
                    ->relationship('employee', 'employee_number', fn ($query) => $query->where('is_active', true))
                    ->getOptionLabelFromRecordUsing(fn (Employee $record): string => "{$record->employee_number} - {$record->first_name} {$record->last_name}")
                    ->required()
                    ->searchable()
                    ->preload(),

                DatePicker::make('attendance_date')
                    ->required()
                    ->default(now()),

                DateTimePicker::make('clock_in_at')
                    ->label('Clock In'),

                DateTimePicker::make('clock_out_at')
                    ->label('Clock Out'),

                TextInput::make('break_minutes')
                    ->numeric()
                    ->default(0)
                    ->minValue(0),

                Select::make('status')
                    ->options([
                        'OPEN' => 'Open',
                        'APPROVED' => 'Approved',
                        'REJECTED' => 'Rejected',
                    ])
                    ->required()
                    ->default('OPEN'),

                Textarea::make('approval_note')
                    ->columnSpanFull(),
            ]);
    }
}
