<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftSwapRequests\Schemas;

use App\Models\Employee;
use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceShiftSwapRequest;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class WorkforceShiftSwapRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Swap Participants')
                    ->icon('heroicon-o-users')
                    ->collapsible()
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('requester_employee_id')
                                    ->label('Requesting Employee')
                                    ->relationship('requester', 'full_name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('requester_roster_assignment_id', null);
                                    }),

                                Select::make('requester_roster_assignment_id')
                                    ->label('Requester Shift to Swap')
                                    ->options(function (Get $get): array {
                                        $employeeId = $get('requester_employee_id');
                                        if (! $employeeId) {
                                            return [];
                                        }

                                        return WorkforceRosterAssignment::query()
                                            ->where('employee_id', $employeeId)
                                            ->whereDate('expected_start_at', '>=', now()->subDays(7))
                                            ->whereDate('expected_end_at', '<=', now()->addDays(30))
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
                                    ->disabled(fn (Get $get): bool => ! $get('requester_employee_id'))
                                    ->placeholder('Select employee first'),

                                Select::make('target_employee_id')
                                    ->label('Target Employee')
                                    ->relationship('target', 'full_name')
                                    ->required()
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->live()
                                    ->afterStateUpdated(function (Set $set) {
                                        $set('target_roster_assignment_id', null);
                                    }),

                                Select::make('target_roster_assignment_id')
                                    ->label('Target Shift to Swap')
                                    ->options(function (Get $get): array {
                                        $employeeId = $get('target_employee_id');
                                        if (! $employeeId) {
                                            return [];
                                        }

                                        return WorkforceRosterAssignment::query()
                                            ->where('employee_id', $employeeId)
                                            ->whereDate('expected_start_at', '>=', now()->subDays(7))
                                            ->whereDate('expected_end_at', '<=', now()->addDays(30))
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
                                    ->disabled(fn (Get $get): bool => ! $get('target_employee_id'))
                                    ->placeholder('Select employee first'),
                            ]),
                    ]),

                Section::make('Swap Details')
                    ->icon('heroicon-o-arrows-right-left')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('swap_type')
                                    ->label('Swap Type')
                                    ->options([
                                        'direct' => 'Direct Swap',
                                        'coverage' => 'Coverage (Takeover)',
                                        'partial' => 'Partial Shift Swap',
                                    ])
                                    ->required()
                                    ->native(false)
                                    ->default('direct'),

                                Select::make('status')
                                    ->label('Status')
                                    ->options(self::getStatusOptions())
                                    ->default(WorkforceShiftSwapRequest::STATUS_DRAFT)
                                    ->required()
                                    ->native(false)
                                    ->disabled(fn (string $operation): bool => $operation === 'create'),

                                DateTimePicker::make('expires_at')
                                    ->label('Expires At')
                                    ->native(false)
                                    ->displayFormat('M d, Y H:i')
                                    ->minDate(now())
                                    ->default(now()->addDays(3))
                                    ->helperText('Request auto-expires if not acted upon by this time.'),
                            ]),

                        Textarea::make('reason')
                            ->label('Reason for Swap')
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(3)
                            ->placeholder('Explain why this swap is needed...')
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
                                TextEntry::make('accepted_by')
                                    ->label('Accepted By')
                                    ->state(fn (WorkforceShiftSwapRequest $record): string => $record->accepted_by ? Employee::find($record->accepted_by)?->full_name ?? 'Unknown' : '—'
                                    ),

                                TextEntry::make('accepted_at')
                                    ->label('Accepted At')
                                    ->state(fn (WorkforceShiftSwapRequest $record): string => $record->accepted_at?->format('M d, Y H:i') ?? '—'
                                    ),

                                TextEntry::make('approved_by')
                                    ->label('Approved By')
                                    ->state(fn (WorkforceShiftSwapRequest $record): string => $record->approved_by ? Employee::find($record->approved_by)?->full_name ?? 'Unknown' : '—'
                                    ),

                                TextEntry::make('approved_at')
                                    ->label('Approved At')
                                    ->state(fn (WorkforceShiftSwapRequest $record): string => $record->approved_at?->format('M d, Y H:i') ?? '—'
                                    ),

                                TextEntry::make('rejected_by')
                                    ->label('Rejected By')
                                    ->state(fn (WorkforceShiftSwapRequest $record): string => $record->rejected_by ? Employee::find($record->rejected_by)?->full_name ?? 'Unknown' : '—'
                                    ),

                                TextEntry::make('rejected_at')
                                    ->label('Rejected At')
                                    ->state(fn (WorkforceShiftSwapRequest $record): string => $record->rejected_at?->format('M d, Y H:i') ?? '—'
                                    ),
                            ]),

                        Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->placeholder('No rejection reason recorded.')
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    private static function getStatusOptions(): array
    {
        return [
            WorkforceShiftSwapRequest::STATUS_DRAFT => 'Draft',
            WorkforceShiftSwapRequest::STATUS_SUBMITTED => 'Submitted',
            WorkforceShiftSwapRequest::STATUS_AWAITING_EMPLOYEE_ACCEPTANCE => 'Awaiting Employee Acceptance',
            WorkforceShiftSwapRequest::STATUS_ACCEPTED_BY_EMPLOYEE => 'Accepted by Employee',
            WorkforceShiftSwapRequest::STATUS_MANAGER_REVIEW => 'Manager Review',
            WorkforceShiftSwapRequest::STATUS_APPROVED => 'Approved',
            WorkforceShiftSwapRequest::STATUS_REJECTED => 'Rejected',
            WorkforceShiftSwapRequest::STATUS_CANCELLED => 'Cancelled',
            WorkforceShiftSwapRequest::STATUS_EXPIRED => 'Expired',
        ];
    }
}
