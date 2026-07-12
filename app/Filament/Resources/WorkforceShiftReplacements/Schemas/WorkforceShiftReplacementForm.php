<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftReplacements\Schemas;

use App\Models\Employee;
use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceShiftReplacement;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WorkforceShiftReplacementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Original Assignment')
                    ->icon('heroicon-o-user')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('original_employee_id')
                                    ->label('Original Employee')
                                    ->relationship('originalEmployee', 'full_name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('original_roster_assignment_id', null);
                                    }),

                                Select::make('original_roster_assignment_id')
                                    ->label('Original Roster Assignment')
                                    ->options(function (Get $get): array {
                                        $employeeId = $get('original_employee_id');
                                        if (! $employeeId) {
                                            return [];
                                        }

                                        return WorkforceRosterAssignment::query()
                                            ->where('employee_id', $employeeId)
                                            ->whereDate('roster_date', '>=', now()->subDays(7))
                                            ->whereDate('roster_date', '<=', now()->addDays(30))
                                            ->with('shift')
                                            ->get()
                                            ->mapWithKeys(fn (WorkforceRosterAssignment $assignment) => [
                                                $assignment->id => "{$assignment->shift->name} — {$assignment->roster_date->format('D, M d')}",
                                            ])
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->disabled(fn (Get $get): bool => ! $get('original_employee_id'))
                                    ->placeholder('Select employee first'),
                            ]),
                    ]),

                Section::make('Replacement Details')
                    ->icon('heroicon-o-user-plus')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('replacement_employee_id')
                                    ->label('Replacement Employee')
                                    ->relationship('replacementEmployee', 'full_name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('replacement_roster_assignment_id', null);
                                    }),

                                Select::make('replacement_roster_assignment_id')
                                    ->label('Replacement Roster Assignment')
                                    ->options(function (Get $get): array {
                                        $employeeId = $get('replacement_employee_id');
                                        if (! $employeeId) {
                                            return [];
                                        }

                                        return WorkforceRosterAssignment::query()
                                            ->where('employee_id', $employeeId)
                                            ->whereDate('roster_date', '>=', now()->subDays(7))
                                            ->whereDate('roster_date', '<=', now()->addDays(30))
                                            ->with('shift')
                                            ->get()
                                            ->mapWithKeys(fn (WorkforceRosterAssignment $assignment) => [
                                                $assignment->id => "{$assignment->shift->name} — {$assignment->roster_date->format('D, M d')}",
                                            ])
                                            ->toArray();
                                    })
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->disabled(fn (Get $get): bool => ! $get('replacement_employee_id'))
                                    ->placeholder('Select employee first'),
                            ]),

                        Grid::make(2)
                            ->schema([
                                Select::make('replacement_type')
                                    ->label('Replacement Type')
                                    ->options([
                                        'temporary' => 'Temporary',
                                        'permanent' => 'Permanent',
                                        'emergency' => 'Emergency',
                                        'voluntary' => 'Voluntary',
                                        'mandatory' => 'Mandatory',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->default('temporary'),

                                Select::make('status')
                                    ->label('Status')
                                    ->options([
                                        WorkforceShiftReplacement::STATUS_DRAFT => 'Draft',
                                        WorkforceShiftReplacement::STATUS_PROPOSED => 'Proposed',
                                        WorkforceShiftReplacement::STATUS_ACCEPTED => 'Accepted',
                                        WorkforceShiftReplacement::STATUS_APPROVED => 'Approved',
                                        WorkforceShiftReplacement::STATUS_REJECTED => 'Rejected',
                                        WorkforceShiftReplacement::STATUS_CANCELLED => 'Cancelled',
                                        WorkforceShiftReplacement::STATUS_COMPLETED => 'Completed',
                                    ])
                                    ->default(WorkforceShiftReplacement::STATUS_DRAFT)
                                    ->required()
                                    ->native(false)
                                    ->disabled(fn (string $operation): bool => $operation === 'create'),
                            ]),

                        Toggle::make('may_create_overtime')
                            ->label('May Create Overtime')
                            ->inline(false)
                            ->default(false)
                            ->hintIcon('heroicon-m-clock')
                            ->hint('Allow this replacement to generate overtime hours'),
                    ]),

                Section::make('Justification')
                    ->icon('heroicon-o-document-text')
                    ->schema([
                        Textarea::make('reason')
                            ->label('Reason for Replacement')
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(3)
                            ->placeholder('Explain why this replacement is necessary...')
                            ->columnSpanFull(),
                    ]),

                Section::make('Workflow Tracking')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (string $operation): bool => $operation === 'edit')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextEntry::make('proposed_by')
                                    ->label('Proposed By')
                                    ->state(fn (WorkforceShiftReplacement $record): string => $record->proposed_by ? Employee::find($record->proposed_by)?->full_name ?? 'Unknown' : '—'
                                    ),

                                TextEntry::make('accepted_by')
                                    ->label('Accepted By')
                                    ->state(fn (WorkforceShiftReplacement $record): string => $record->accepted_by ? Employee::find($record->accepted_by)?->full_name ?? 'Unknown' : 'Pending'
                                    ),

                                TextEntry::make('accepted_at')
                                    ->label('Accepted At')
                                    ->state(fn (WorkforceShiftReplacement $record): string => $record->accepted_at?->format('M d, Y H:i') ?? '—'
                                    ),

                                TextEntry::make('approved_by')
                                    ->label('Approved By')
                                    ->state(fn (WorkforceShiftReplacement $record): string => $record->approved_by ? Employee::find($record->approved_by)?->full_name ?? 'Unknown' : 'Pending'
                                    ),

                                TextEntry::make('approved_at')
                                    ->label('Approved At')
                                    ->state(fn (WorkforceShiftReplacement $record): string => $record->approved_at?->format('M d, Y H:i') ?? '—'
                                    ),

                                TextEntry::make('rejected_by')
                                    ->label('Rejected By')
                                    ->state(fn (WorkforceShiftReplacement $record): string => $record->rejected_by ? Employee::find($record->rejected_by)?->full_name ?? 'Unknown' : '—'
                                    ),

                                TextEntry::make('rejected_at')
                                    ->label('Rejected At')
                                    ->state(fn (WorkforceShiftReplacement $record): string => $record->rejected_at?->format('M d, Y H:i') ?? '—'
                                    ),

                                Textarea::make('rejection_reason')
                                    ->label('Rejection Reason')
                                    ->placeholder('No rejection reason recorded.')
                                    ->rows(2)
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
