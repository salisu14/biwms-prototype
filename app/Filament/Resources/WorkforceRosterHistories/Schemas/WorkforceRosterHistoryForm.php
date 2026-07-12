<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterHistories\Schemas;

use App\Models\WorkforceRosterAssignment;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class WorkforceRosterHistoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Roster Context')
                    ->icon('heroicon-o-calendar')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('workforce_roster_period_id')
                                    ->label('Roster Period')
                                    ->relationship('workforceRosterPeriod', 'name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('workforce_roster_assignment_id', null);
                                    }),

                                Select::make('workforce_roster_assignment_id')
                                    ->label('Roster Assignment')
                                    ->relationship(
                                        name: 'workforceRosterAssignment',
                                        titleAttribute: 'id',
                                        modifyQueryUsing: function (Builder $query, Get $get) {
                                            $periodId = $get('workforce_roster_period_id');
                                            if ($periodId) {
                                                $query->where('workforce_roster_period_id', $periodId);
                                            }
                                        }
                                    )
                                    ->getOptionLabelFromRecordUsing(fn (WorkforceRosterAssignment $record): string => "{$record->employee->full_name} — {$record->work_date->format('D, M d')} ({$record->shift->name})"
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->disabled(fn (Get $get): bool => ! $get('workforce_roster_period_id'))
                                    ->placeholder('Select period first'),
                            ]),

                        Select::make('employee_id')
                            ->label('Affected Employee')
                            ->relationship('employee', 'full_name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->native(false),
                    ]),

                Section::make('Event Details')
                    ->icon('heroicon-o-bolt')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('event_type')
                                    ->label('Event Type')
                                    ->options([
                                        'created' => 'Created',
                                        'updated' => 'Updated',
                                        'deleted' => 'Deleted',
                                        'published' => 'Published',
                                        'cancelled' => 'Cancelled',
                                        'replaced' => 'Replaced',
                                        'swapped' => 'Swapped',
                                        'approved' => 'Approved',
                                        'rejected' => 'Rejected',
                                        'shift_changed' => 'Shift Changed',
                                        'location_changed' => 'Location Changed',
                                        'role_changed' => 'Role Changed',
                                        'overtime_flagged' => 'Overtime Flagged',
                                        'conflict_detected' => 'Conflict Detected',
                                        'resolved' => 'Resolved',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->searchable(),

                                DateTimePicker::make('changed_at')
                                    ->label('Changed At')
                                    ->required()
                                    ->native(false)
                                    ->displayFormat('M d, Y H:i:s')
                                    ->default(now())
                                    ->closeOnDateSelection(),

                                Select::make('changed_by')
                                    ->label('Changed By')
                                    ->relationship('changedBy', 'name')
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->placeholder('System / Automated'),
                            ]),

                        Textarea::make('reason')
                            ->label('Reason for Change')
                            ->required()
                            ->minLength(5)
                            ->maxLength(1000)
                            ->rows(3)
                            ->placeholder('Explain what triggered this roster change...')
                            ->columnSpanFull(),
                    ]),

                Section::make('Value Changes')
                    ->icon('heroicon-o-arrows-right-left')
                    ->collapsible()
                    ->schema([
                        KeyValue::make('before_values')
                            ->label('Before')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                        //                            ->placeholder('No previous values recorded'),

                        KeyValue::make('after_values')
                            ->label('After')
                            ->keyLabel('Field')
                            ->valueLabel('Value')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false),
                        //                            ->placeholder('No new values recorded'),
                    ]),

                Section::make('Processing Flags')
                    ->icon('heroicon-o-flag')
                    ->columns(3)
                    ->schema([
                        Toggle::make('employee_notified')
                            ->label('Employee Notified')
                            ->inline(false)
                            ->default(false)
                            ->hintIcon('heroicon-m-bell')
                            ->hint('Employee received notification of this change'),

                        Toggle::make('attendance_recalculated')
                            ->label('Attendance Recalculated')
                            ->inline(false)
                            ->default(false)
                            ->hintIcon('heroicon-m-calculator')
                            ->hint('Attendance hours were recalculated'),

                        Toggle::make('attendance_period_locked')
                            ->label('Period Locked')
                            ->inline(false)
                            ->default(false)
                            ->hintIcon('heroicon-m-lock-closed')
                            ->hint('Attendance period is locked after this change'),
                    ]),
            ]);
    }
}
