<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceShiftReplacements\Schemas;

use App\Models\Employee;
use App\Models\WorkforceShiftReplacement;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\TextSize;

class WorkforceShiftReplacementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overview')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                WorkforceShiftReplacement::STATUS_DRAFT => 'gray',
                                WorkforceShiftReplacement::STATUS_PROPOSED => 'primary',
                                WorkforceShiftReplacement::STATUS_ACCEPTED => 'info',
                                WorkforceShiftReplacement::STATUS_APPROVED => 'success',
                                WorkforceShiftReplacement::STATUS_COMPLETED => 'success',
                                WorkforceShiftReplacement::STATUS_REJECTED => 'danger',
                                WorkforceShiftReplacement::STATUS_CANCELLED => 'secondary',
                                default => 'gray',
                            })
                            ->icon(fn (string $state): string => match ($state) {
                                WorkforceShiftReplacement::STATUS_DRAFT => 'heroicon-o-pencil',
                                WorkforceShiftReplacement::STATUS_PROPOSED => 'heroicon-o-paper-airplane',
                                WorkforceShiftReplacement::STATUS_ACCEPTED => 'heroicon-o-hand-thumb-up',
                                WorkforceShiftReplacement::STATUS_APPROVED => 'heroicon-o-check-badge',
                                WorkforceShiftReplacement::STATUS_COMPLETED => 'heroicon-o-flag',
                                WorkforceShiftReplacement::STATUS_REJECTED => 'heroicon-o-x-circle',
                                WorkforceShiftReplacement::STATUS_CANCELLED => 'heroicon-o-archive-box',
                                default => 'heroicon-o-question-mark-circle',
                            }),

                        TextEntry::make('replacement_type')
                            ->label('Type')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ucfirst($state))
                            ->color(fn (string $state): string => match ($state) {
                                'temporary' => 'primary',
                                'permanent' => 'success',
                                'emergency' => 'danger',
                                'voluntary' => 'info',
                                'mandatory' => 'warning',
                                default => 'gray',
                            }),

                        IconEntry::make('may_create_overtime')
                            ->label('Overtime')
                            ->boolean()
                            ->trueIcon('heroicon-o-clock')
                            ->falseIcon('heroicon-o-minus')
                            ->trueColor('warning')
                            ->falseColor('gray'),
                        //                            ->trueLabel('May create overtime')
                        //                            ->falseLabel('No overtime'),
                    ]),

                Section::make('Original Assignment')
                    ->icon('heroicon-o-user')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('originalEmployee.full_name')
                            ->label('Employee')
                            ->icon('heroicon-o-user')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('originalAssignment.shift.name')
                            ->label('Shift')
                            ->badge()
                            ->color('info')
                            ->icon('heroicon-o-sun'),

                        TextEntry::make('originalAssignment.roster_date')
                            ->label('Roster Date')
                            ->date('l, F j, Y')
                            ->icon('heroicon-o-calendar'),

                        TextEntry::make('originalAssignment.location.name')
                            ->label('Location')
                            ->placeholder('Not assigned')
                            ->icon('heroicon-o-map-pin'),
                    ]),

                Section::make('Replacement Assignment')
                    ->icon('heroicon-o-user-plus')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('replacementEmployee.full_name')
                            ->label('Replacement Employee')
                            ->icon('heroicon-o-user')
                            ->weight('font-bold')
                            ->size(TextSize::Large),

                        TextEntry::make('replacementAssignment.shift.name')
                            ->label('Shift')
                            ->badge()
                            ->color('warning')
                            ->icon('heroicon-o-sun'),

                        TextEntry::make('replacementAssignment.roster_date')
                            ->label('Roster Date')
                            ->date('l, F j, Y')
                            ->icon('heroicon-o-calendar'),

                        TextEntry::make('replacementAssignment.location.name')
                            ->label('Location')
                            ->placeholder('Not assigned')
                            ->icon('heroicon-o-map-pin'),
                    ]),

                Section::make('Justification')
                    ->icon('heroicon-o-document-text')
                    ->collapsible()
                    ->schema([
                        TextEntry::make('reason')
                            ->label('Reason for Replacement')
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
                        TextEntry::make('proposed_by')
                            ->label('Proposed By')
                            ->formatStateUsing(fn (?int $state): string => $state ? Employee::find($state)?->full_name ?? 'Unknown' : '—'
                            )
                            ->icon('heroicon-o-user-circle')
                            ->placeholder('—'),

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
                            ->color('danger')
                            ->placeholder('—'),

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
                    ->visible(fn (WorkforceShiftReplacement $record): bool => $record->status === WorkforceShiftReplacement::STATUS_REJECTED
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
