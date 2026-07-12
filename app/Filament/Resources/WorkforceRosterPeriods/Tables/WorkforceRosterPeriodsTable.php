<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceRosterPeriods\Tables;

use App\Models\WorkforceRosterPeriod;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WorkforceRosterPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date_from', 'desc')
            ->columns(self::getTableColumns())
            ->filters(self::getTableFilters())
            ->recordActions(self::getRecordActions())
            ->toolbarActions(self::getBulkActions())
            ->emptyStateIcon('heroicon-o-calendar-days')
            ->emptyStateDescription('No roster periods found.')
            ->emptyStateActions([
                Action::make('create')
                    ->label('Create Roster Period')
                    ->url(route('filament.admin.resources.workforce-roster-periods.create'))
                    ->button()
                    ->icon('heroicon-o-plus'),
            ]);
    }

    private static function getTableColumns(): array
    {
        return [
            self::makeCodeColumn(),
            self::makeNameColumn(),
            self::makeDateRangeColumn(),
            self::makeStatusColumn(),
            self::makeDepartmentColumn(),
            self::makeWorkCenterColumn(),
            self::makeAssignmentCountColumn(),
            self::makePublishedAtColumn(),
        ];
    }

    private static function makeCodeColumn(): TextColumn
    {
        return TextColumn::make('code')
            ->label('Code')
            ->searchable()
            ->sortable()
            ->weight('bold');
        //            ->toggleable(isToggledByDefault: true);
    }

    private static function makeNameColumn(): TextColumn
    {
        return TextColumn::make('name')
            ->label('Period Name')
            ->searchable()
            ->sortable()
            ->limit(40)
            ->tooltip(function (TextColumn $column): ?string {
                $state = $column->getState();

                if (strlen($state) <= $column->getCharacterLimit()) {
                    return null;
                }

                return $state;
            });
        //            ->toggleable(isToggledByDefault: true);
    }

    private static function makeDateRangeColumn(): TextColumn
    {
        return TextColumn::make('date_range')
            ->label('Date Range')
            ->getStateUsing(function (WorkforceRosterPeriod $record): string {
                $from = Carbon::parse($record->date_from)->format('M d');
                $to = Carbon::parse($record->date_to)->format('M d, Y');

                return "{$from} – {$to}";
            })
            ->sortable(query: function ($query, string $direction): void {
                $query->orderBy('date_from', $direction);
            })
            ->description(function (WorkforceRosterPeriod $record): string {
                $days = Carbon::parse($record->date_from)
                    ->diffInDays(Carbon::parse($record->date_to)) + 1;

                return "{$days} days";
            });
        //            ->toggleable(isToggledByDefault: true);
    }

    private static function makeStatusColumn(): TextColumn
    {
        return TextColumn::make('status')
            ->label('Status')
            ->badge()
            ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', ucfirst($state)))
            ->color(fn (string $state): string => match ($state) {
                WorkforceRosterPeriod::STATUS_DRAFT => 'gray',
                WorkforceRosterPeriod::STATUS_GENERATED => 'info',
                WorkforceRosterPeriod::STATUS_UNDER_REVIEW => 'warning',
                WorkforceRosterPeriod::STATUS_PUBLISHED,
                WorkforceRosterPeriod::STATUS_ACTIVE => 'success',
                WorkforceRosterPeriod::STATUS_CLOSED => 'primary',
                WorkforceRosterPeriod::STATUS_CANCELLED => 'danger',
                WorkforceRosterPeriod::STATUS_REOPENED => 'warning',
                default => 'gray',
            })
            ->icon(fn (string $state): string => match ($state) {
                WorkforceRosterPeriod::STATUS_DRAFT => 'heroicon-o-document-text',
                WorkforceRosterPeriod::STATUS_GENERATED => 'heroicon-o-cog',
                WorkforceRosterPeriod::STATUS_UNDER_REVIEW => 'heroicon-o-eye',
                WorkforceRosterPeriod::STATUS_PUBLISHED,
                WorkforceRosterPeriod::STATUS_ACTIVE => 'heroicon-o-check-circle',
                WorkforceRosterPeriod::STATUS_CLOSED => 'heroicon-o-lock-closed',
                WorkforceRosterPeriod::STATUS_CANCELLED => 'heroicon-o-x-circle',
                WorkforceRosterPeriod::STATUS_REOPENED => 'heroicon-o-arrow-path',
                default => 'heroicon-o-question-mark-circle',
            })
            ->sortable();
        //            ->toggleable(isToggledByDefault: true);
    }

    private static function makeDepartmentColumn(): TextColumn
    {
        return TextColumn::make('department.name')
            ->label('Department')
            ->searchable()
            ->sortable()
            ->toggleable()
            ->placeholder('—');
    }

    private static function makeWorkCenterColumn(): TextColumn
    {
        return TextColumn::make('workCenter.name')
            ->label('Work Center')
            ->searchable()
            ->sortable()
            ->toggleable()
            ->placeholder('—');
    }

    private static function makeAssignmentCountColumn(): TextColumn
    {
        return TextColumn::make('assignments_count')
            ->label('Assignments')
            ->counts('assignments')
            ->sortable()
            ->toggleable()
            ->formatStateUsing(fn (int $state): string => number_format($state))
            ->icon('heroicon-o-users')
            ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray');
    }

    private static function makePublishedAtColumn(): TextColumn
    {
        return TextColumn::make('published_at')
            ->label('Published')
            ->dateTime('M d, Y')
            ->sortable()
            ->toggleable()
            ->placeholder('Not published')
            ->icon('heroicon-o-check-badge')
            ->color('success');
    }

    // ==================== FILTERS ====================

    private static function getTableFilters(): array
    {
        return [
            self::makeStatusFilter(),
            self::makeDepartmentFilter(),
            self::makeDateRangeFilter(),
            self::makeActivePeriodsFilter(),
        ];
    }

    private static function makeStatusFilter(): SelectFilter
    {
        return SelectFilter::make('status')
            ->label('Status')
            ->options([
                WorkforceRosterPeriod::STATUS_DRAFT => 'Draft',
                WorkforceRosterPeriod::STATUS_GENERATED => 'Generated',
                WorkforceRosterPeriod::STATUS_UNDER_REVIEW => 'Under Review',
                WorkforceRosterPeriod::STATUS_PUBLISHED => 'Published',
                WorkforceRosterPeriod::STATUS_ACTIVE => 'Active',
                WorkforceRosterPeriod::STATUS_CLOSED => 'Closed',
                WorkforceRosterPeriod::STATUS_CANCELLED => 'Cancelled',
                WorkforceRosterPeriod::STATUS_REOPENED => 'Reopened',
            ])
            ->multiple()
            ->default([WorkforceRosterPeriod::STATUS_DRAFT, WorkforceRosterPeriod::STATUS_ACTIVE]);
    }

    private static function makeDepartmentFilter(): SelectFilter
    {
        return SelectFilter::make('department_id')
            ->label('Department')
            ->relationship('department', 'name')
            ->searchable()
            ->preload();
    }

    private static function makeDateRangeFilter(): Filter
    {
        return Filter::make('date_range')
            ->label('Custom Date Range')
            ->schema([
                DatePicker::make('date_from')
                    ->label('From'),
                DatePicker::make('date_to')
                    ->label('To'),
            ])
            ->query(function ($query, array $data): void {
                $data['date_from'] ??= null;
                $data['date_to'] ??= null;

                $query
                    ->when(
                        $data['date_from'],
                        fn ($q) => $q->whereDate('date_from', '>=', $data['date_from'])
                    )
                    ->when(
                        $data['date_to'],
                        fn ($q) => $q->whereDate('date_to', '<=', $data['date_to'])
                    );
            });
    }

    private static function makeActivePeriodsFilter(): Filter
    {
        return Filter::make('active_periods')
            ->label('Active Periods Only')
            ->query(fn ($query): mixed => $query->whereIn('status', [
                WorkforceRosterPeriod::STATUS_ACTIVE,
                WorkforceRosterPeriod::STATUS_PUBLISHED,
            ]))
            ->default(false)
            ->toggle();
    }

    // ==================== ACTIONS ====================

    private static function getRecordActions(): array
    {
        return [
            ViewAction::make(),

            EditAction::make()
                ->visible(fn (WorkforceRosterPeriod $record): bool => in_array($record->status, [WorkforceRosterPeriod::STATUS_DRAFT])
                ),

            ActionGroup::make([
                Action::make('generate')
                    ->label('Generate Assignments')
                    ->icon('heroicon-o-cog')
                    ->requiresConfirmation()
                    ->color('info')
                    ->visible(fn (WorkforceRosterPeriod $record): bool => $record->status === WorkforceRosterPeriod::STATUS_DRAFT
                    )
                    ->action(function (WorkforceRosterPeriod $record) {
                        // Trigger generation logic here
                        $record->update([
                            'status' => WorkforceRosterPeriod::STATUS_GENERATED,
                            'generated_at' => now(),
                            'generated_by' => auth()->id(),
                        ]);
                    }),

                Action::make('publish')
                    ->label('Publish Roster')
                    ->icon('heroicon-o-check-circle')
                    ->requiresConfirmation()
//                    ->confirmationTitle('Publish this roster period?')
//                    ->confirmationQuestion('This will notify all assigned employees. Are you sure?')
                    ->color('success')
                    ->visible(fn (WorkforceRosterPeriod $record): bool => in_array($record->status, [
                        WorkforceRosterPeriod::STATUS_GENERATED,
                        WorkforceRosterPeriod::STATUS_UNDER_REVIEW,
                    ])
                    )
                    ->action(function (WorkforceRosterPeriod $record) {
                        $record->update([
                            'status' => WorkforceRosterPeriod::STATUS_PUBLISHED,
                            'published_at' => now(),
                            'published_by' => auth()->id(),
                        ]);
                    }),

                Action::make('close')
                    ->label('Close Period')
                    ->icon('heroicon-o-lock-closed')
                    ->requiresConfirmation()
                    ->color('warning')
                    ->visible(fn (WorkforceRosterPeriod $record): bool => in_array($record->status, [WorkforceRosterPeriod::STATUS_ACTIVE, WorkforceRosterPeriod::STATUS_PUBLISHED])
                    )
                    ->action(function (WorkforceRosterPeriod $record) {
                        $record->update([
                            'status' => WorkforceRosterPeriod::STATUS_CLOSED,
                            'closed_at' => now(),
                            'closed_by' => auth()->id(),
                        ]);
                    }),

                Action::make('reopen')
                    ->label('Reopen Period')
                    ->icon('heroicon-o-arrow-path')
                    ->schema([
                        Textarea::make('reopen_reason')
                            ->label('Reason for Reopening')
                            ->required()
                            ->rows(2),
                    ])
                    ->action(function (array $data, WorkforceRosterPeriod $record) {
                        $record->update([
                            'status' => WorkforceRosterPeriod::STATUS_REOPENED,
                            'reopened_at' => now(),
                            'reopened_by' => auth()->id(),
                            'reopen_reason' => $data['reopen_reason'],
                        ]);
                    })
                    ->visible(fn (WorkforceRosterPeriod $record): bool => $record->status === WorkforceRosterPeriod::STATUS_CLOSED
                    ),
            ])->dropdown(),
        ];
    }

    private static function getBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make()
                    ->visible(fn (): bool => auth()->user()->can('delete_any_workforce_roster_period')),
            ]),
        ];
    }
}
