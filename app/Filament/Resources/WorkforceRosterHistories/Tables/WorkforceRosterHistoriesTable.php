<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterHistories\Tables;

use App\Models\WorkforceRosterHistory;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WorkforceRosterHistoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('changed_at', 'desc')
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->width('60px')
                    ->alignCenter(),

                TextColumn::make('workforceRosterPeriod.name')
                    ->label('Period')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->limit(20)
                    ->tooltip(fn (WorkforceRosterHistory $record): ?string => $record->workforceRosterPeriod?->name),

                TextColumn::make('employee.full_name')
                    ->label('Employee')
                    ->sortable()
                    ->searchable()
                    ->weight('font-medium'),

                TextColumn::make('workforceRosterAssignment.work_date')
                    ->label('Work Date')
                    ->date('M d, Y')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('event_type')
                    ->label('Event')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucwords(str_replace('_', ' ', $state)))
                    ->colors([
                        'success' => ['created', 'published', 'approved', 'resolved'],
                        'warning' => ['updated', 'shift_changed', 'location_changed', 'role_changed', 'overtime_flagged'],
                        'danger' => ['deleted', 'cancelled', 'rejected', 'conflict_detected'],
                        'info' => ['replaced', 'swapped'],
                        'gray' => ['submitted'],
                    ])
                    ->icons([
                        'heroicon-o-plus-circle' => 'created',
                        'heroicon-o-pencil' => 'updated',
                        'heroicon-o-trash' => 'deleted',
                        'heroicon-o-paper-airplane' => 'published',
                        'heroicon-o-x-circle' => 'cancelled',
                        'heroicon-o-arrow-path' => 'replaced',
                        'heroicon-o-arrows-right-left' => 'swapped',
                        'heroicon-o-check-badge' => 'approved',
                        'heroicon-o-no-symbol' => 'rejected',
                        'heroicon-o-clock' => 'shift_changed',
                        'heroicon-o-map-pin' => 'location_changed',
                        'heroicon-o-user-circle' => 'role_changed',
                        'heroicon-o-bolt' => 'overtime_flagged',
                        'heroicon-o-exclamation-triangle' => 'conflict_detected',
                        'heroicon-o-check-circle' => 'resolved',
                    ])
                    ->sortable(),

                TextColumn::make('changedBy.name')
                    ->label('Changed By')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->placeholder('System'),

                TextColumn::make('changed_at')
                    ->label('Changed At')
                    ->dateTime('M d, H:i')
                    ->sortable()
                    ->description(fn (WorkforceRosterHistory $record): string => $record->changed_at->diffForHumans()
                    ),

                IconColumn::make('employee_notified')
                    ->label('Notified')
                    ->boolean()
                    ->trueIcon('heroicon-o-bell')
                    ->falseIcon('heroicon-o-bell-slash')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->toggleable(),

                IconColumn::make('attendance_recalculated')
                    ->label('Recalc')
                    ->boolean()
                    ->trueIcon('heroicon-o-calculator')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('info')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                IconColumn::make('attendance_period_locked')
                    ->label('Locked')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('danger')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('workforce_roster_period_id')
                    ->label('Roster Period')
                    ->relationship('workforceRosterPeriod', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('event_type')
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
                    ->multiple()
                    ->native(false),

                SelectFilter::make('changed_by')
                    ->label('Changed By')
                    ->relationship('changedBy', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                TernaryFilter::make('employee_notified')
                    ->label('Employee Notified')
                    ->placeholder('All')
                    ->trueLabel('Notified')
                    ->falseLabel('Not notified'),

                TernaryFilter::make('attendance_period_locked')
                    ->label('Period Locked')
                    ->placeholder('All')
                    ->trueLabel('Locked')
                    ->falseLabel('Unlocked'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([])
            ->emptyStateHeading('No roster history')
            ->emptyStateDescription('History entries are generated automatically when roster assignments change.')
            ->emptyStateIcon('heroicon-o-clock');
    }
}
