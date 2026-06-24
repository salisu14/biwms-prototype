<?php

namespace App\Filament\Resources\AttendanceLedgerEntries\Schemas;

use App\Models\AttendanceLedgerEntry;
use App\Models\Employee;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class AttendanceLedgerEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Employee & Date')
                    ->icon('heroicon-m-user-group')
                    ->schema([
                        Select::make('employee_id')
                            ->label('Employee')
                            ->relationship('employee', 'employee_number', fn ($query) => $query->where('is_active', true))
                            ->getOptionLabelFromRecordUsing(fn (Employee $record): string => "{$record->employee_number} - {$record->first_name} {$record->last_name}")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(2),

                        DatePicker::make('attendance_date')
                            ->required()
                            ->default(now())
                            ->columnSpan(1),
                    ])->columns(3),

                Section::make('Time Record')
                    ->icon('heroicon-m-clock')
                    ->description('Enter clock in and out times. Worked hours are calculated automatically.')
                    ->schema([
                        Grid::make(3)->schema([
                            DateTimePicker::make('clock_in_at')
                                ->label('Clock In')
                                ->seconds(false) // Removes seconds picker for better UX
                                ->live()
                                ->columnSpan(1),

                            DateTimePicker::make('clock_out_at')
                                ->label('Clock Out')
                                ->seconds(false)
                                ->live()
                                ->columnSpan(1),

                            TextInput::make('break_minutes')
                                ->label('Break (mins)')
                                ->numeric()
                                ->default(0)
                                ->minValue(0)
                                ->maxValue(480) // 8 hours max break
                                ->live()
                                ->columnSpan(1),
                        ]),

                        // Live Preview: This field is NOT saved to the DB (dehydrated(false)),
                        // it just mirrors the calculation that happens in the Model's `saving()` event.
                        TextInput::make('calculated_hours_preview')
                            ->label('Total Worked Hours')
                            ->disabled()
                            ->dehydrated(false)
                            ->suffix('hrs')
                            ->formatStateUsing(function (Get $get): string {
                                $in = $get('clock_in_at');
                                $out = $get('clock_out_at');
                                $break = (int) ($get('break_minutes') ?? 0);

                                if ($in && $out) {
                                    $minutes = Carbon::parse($in)->diffInMinutes(Carbon::parse($out));
                                    $netMinutes = max(0, $minutes - $break);
                                    return number_format(round($netMinutes / 60, 2), 2);
                                }

                                return '0.00';
                            })
                            ->columnSpanFull()
                            ->helperText('Calculated automatically based on Clock In/Out and Break minutes.'),
                    ]),

                Section::make('Approval')
                    ->icon('heroicon-m-check-circle')
                    // Only show the approval section when EDITING an existing record.
                    // New entries should always default to 'OPEN' in the database without user interference.
                    ->visible(fn (?AttendanceLedgerEntry $record): bool => $record !== null)
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('status')
                                ->options([
                                    'OPEN' => 'Open',
                                    'APPROVED' => 'Approved',
                                    'REJECTED' => 'Rejected',
                                ])
                                ->required()
                                ->columnSpan(1),

                            TextEntry::make('approved_by_name')
                                ->label('Approved By')
                                ->state(fn ($record) => $record->approver?->name ?? 'N/A')
                                ->visibleOn('edit'), // Only shows the actual approver name on the edit form
                        ]),

                        Textarea::make('approval_note')
                            ->label('Approval Note / Rejection Reason')
                            ->placeholder('Enter a reason if rejecting, or a note for approval...')
                            ->columnSpanFull()
                            ->rows(3),
                    ]),
            ]);
    }
}
