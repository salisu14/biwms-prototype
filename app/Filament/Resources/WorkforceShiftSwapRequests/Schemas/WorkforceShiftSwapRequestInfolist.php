<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftSwapRequests\Schemas;

use App\Models\Employee;
use App\Models\WorkforceShiftSwapRequest;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class WorkforceShiftSwapRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Swap Overview')
                    ->icon('heroicon-o-arrows-right-left')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                WorkforceShiftSwapRequest::STATUS_DRAFT => 'gray',
                                WorkforceShiftSwapRequest::STATUS_SUBMITTED => 'primary',
                                WorkforceShiftSwapRequest::STATUS_AWAITING_EMPLOYEE_ACCEPTANCE => 'warning',
                                WorkforceShiftSwapRequest::STATUS_ACCEPTED_BY_EMPLOYEE => 'info',
                                WorkforceShiftSwapRequest::STATUS_MANAGER_REVIEW => 'danger',
                                WorkforceShiftSwapRequest::STATUS_APPROVED => 'success',
                                WorkforceShiftSwapRequest::STATUS_REJECTED => 'danger',
                                WorkforceShiftSwapRequest::STATUS_CANCELLED => 'secondary',
                                WorkforceShiftSwapRequest::STATUS_EXPIRED => 'gray',
                                default => 'gray',
                            })
                            ->icon(fn (string $state): string => match ($state) {
                                WorkforceShiftSwapRequest::STATUS_DRAFT => 'heroicon-o-pencil',
                                WorkforceShiftSwapRequest::STATUS_SUBMITTED => 'heroicon-o-paper-airplane',
                                WorkforceShiftSwapRequest::STATUS_AWAITING_EMPLOYEE_ACCEPTANCE => 'heroicon-o-clock',
                                WorkforceShiftSwapRequest::STATUS_ACCEPTED_BY_EMPLOYEE => 'heroicon-o-hand-thumb-up',
                                WorkforceShiftSwapRequest::STATUS_MANAGER_REVIEW => 'heroicon-o-eye',
                                WorkforceShiftSwapRequest::STATUS_APPROVED => 'heroicon-o-check-badge',
                                WorkforceShiftSwapRequest::STATUS_REJECTED => 'heroicon-o-x-circle',
                                WorkforceShiftSwapRequest::STATUS_CANCELLED => 'heroicon-o-archive-box',
                                WorkforceShiftSwapRequest::STATUS_EXPIRED => 'heroicon-o-x-mark',
                                default => 'heroicon-o-question-mark-circle',
                            }),

                        TextEntry::make('swap_type')
                            ->label('Swap Type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'direct' => 'Direct Swap',
                                'coverage' => 'Coverage (Takeover)',
                                'partial' => 'Partial Shift Swap',
                                default => $state,
                            })
                            ->color(fn (string $state): string => match ($state) {
                                'direct' => 'success',
                                'coverage' => 'warning',
                                'partial' => 'info',
                                default => 'gray',
                            }),

                        TextEntry::make('expires_at')
                            ->label('Expires')
                            ->dateTime('F j, Y \a\t g:i A')
                            ->icon('heroicon-o-clock')
                            ->color(fn (WorkforceShiftSwapRequest $record): string => $record->expires_at && $record->expires_at->isPast()
                                ? 'danger'
                                : 'gray'
                            ),
                    ]),

                Section::make('Requesting Employee')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('requester.full_name')
                            ->label('Employee')
                            ->icon('heroicon-o-user')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('requesterAssignment.shift.name')
                            ->label('Shift to Swap')
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-o-sun'),

                        TextEntry::make('requesterAssignment.roster_date')
                            ->label('Roster Date')
                            ->date('l, F j, Y')
                            ->icon('heroicon-o-calendar'),

                        TextEntry::make('requesterAssignment.location.name')
                            ->label('Location')
                            ->placeholder('Not assigned')
                            ->icon('heroicon-o-map-pin'),
                    ]),

                Section::make('Target Employee')
                    ->icon('heroicon-o-user-group')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('target.full_name')
                            ->label('Employee')
                            ->icon('heroicon-o-user')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('targetAssignment.shift.name')
                            ->label('Shift to Swap')
                            ->badge()
                            ->color('warning')
                            ->icon('heroicon-o-sun'),

                        TextEntry::make('targetAssignment.roster_date')
                            ->label('Roster Date')
                            ->date('l, F j, Y')
                            ->icon('heroicon-o-calendar'),

                        TextEntry::make('targetAssignment.location.name')
                            ->label('Location')
                            ->placeholder('Not assigned')
                            ->icon('heroicon-o-map-pin'),
                    ]),

                Section::make('Reason')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('reason')
                            ->label('Swap Justification')
                            ->markdown()
                            ->prose()
                            ->placeholder('No reason provided')
                            ->columnSpanFull(),
                    ]),

                Section::make('Workflow Timeline')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->collapsible()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('accepted_by')
                            ->label('Accepted By')
                            ->formatStateUsing(fn (?int $state): string => $state ? Employee::find($state)?->full_name ?? 'Unknown' : 'Pending'
                            )
                            ->icon('heroicon-o-hand-thumb-up')
                            ->color(fn (?int $state): string => $state ? 'success' : 'gray'),

                        TextEntry::make('accepted_at')
                            ->label('Accepted At')
                            ->dateTime('M d, Y H:i')
                            ->placeholder('—')
                            ->icon('heroicon-o-clock'),

                        TextEntry::make('approved_by')
                            ->label('Approved By')
                            ->formatStateUsing(fn (?int $state): string => $state ? Employee::find($state)?->full_name ?? 'Unknown' : 'Pending'
                            )
                            ->icon('heroicon-o-check-badge')
                            ->color(fn (?int $state): string => $state ? 'success' : 'gray'),

                        TextEntry::make('approved_at')
                            ->label('Approved At')
                            ->dateTime('M d, Y H:i')
                            ->placeholder('—')
                            ->icon('heroicon-o-clock'),

                        TextEntry::make('rejected_by')
                            ->label('Rejected By')
                            ->formatStateUsing(fn (?int $state): string => $state ? Employee::find($state)?->full_name ?? 'Unknown' : '—'
                            )
                            ->icon('heroicon-o-x-circle')
                            ->color('danger'),

                        TextEntry::make('rejected_at')
                            ->label('Rejected At')
                            ->dateTime('M d, Y H:i')
                            ->placeholder('—')
                            ->icon('heroicon-o-clock'),
                    ]),

                Section::make('Rejection Details')
                    ->icon('heroicon-o-x-mark')
                    ->collapsible()
                    ->collapsed()
                    ->visible(fn (WorkforceShiftSwapRequest $record): bool => $record->status === WorkforceShiftSwapRequest::STATUS_REJECTED
                    )
                    ->schema([
                        TextEntry::make('rejection_reason')
                            ->label('Reason for Rejection')
                            ->placeholder('No reason recorded')
                            ->columnSpanFull(),
                    ]),

                Section::make('Audit Trail')
                    ->icon('heroicon-o-clock')
                    ->collapsible()
                    ->collapsed()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label('Created')
                            ->dateTime('M d, Y H:i')
                            ->icon('heroicon-o-plus-circle'),

                        TextEntry::make('updated_at')
                            ->label('Last Updated')
                            ->dateTime('M d, Y H:i')
                            ->icon('heroicon-o-arrow-path'),
                    ]),
            ]);
    }
}
