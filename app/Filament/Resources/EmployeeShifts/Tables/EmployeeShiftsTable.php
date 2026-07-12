<?php

declare(strict_types=1);

namespace App\Filament\Resources\EmployeeShifts\Tables;

use App\Models\EmployeeShift;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TimePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Throwable;

class EmployeeShiftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('start_time', 'asc')
            ->defaultPaginationPageOption(25)
            ->columns(self::getColumns())
            ->filters(self::getFilters())
            ->recordActions(self::getActions())
            ->toolbarActions(self::getBulkActions())
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateDescription('No shifts found.');
    }

    private static function getColumns(): array
    {
        return [
            TextColumn::make('code')
                ->label('Code')
                ->searchable()
                ->sortable()
                ->weight('bold')
                ->copyable(),

            TextColumn::make('name')
                ->label('Shift Name')
                ->searchable()
                ->sortable(),

            TextColumn::make('time_range')
                ->label('Hours')
                ->getStateUsing(
                    fn (EmployeeShift $record): string => filled($record->start_time) && filled($record->end_time)
                        ? self::formatTime($record->start_time)
                        .' – '
                        .self::formatTime($record->end_time)
                        : '—'
                )
                ->sortable(
                    query: fn (Builder $query, string $direction): Builder => $query->orderBy('start_time', $direction)
                )
                ->badge()
                ->color(
                    fn (EmployeeShift $record): string => $record->crosses_midnight ? 'danger' : 'primary'
                ),

            TextColumn::make('duration_hours')
                ->label('Duration')
                ->getStateUsing(
                    fn (EmployeeShift $record): string => self::calculateDuration($record)
                )
                ->alignment('center')
                ->toggleable(),

            TextColumn::make('break_minutes')
                ->label('Break')
                ->formatStateUsing(
                    fn (mixed $state): string => number_format((int) ($state ?? 0)).'m'
                )
                ->alignment('center')
                ->toggleable(),

            IconColumn::make('is_weekend')
                ->label('Weekend')
                ->boolean()
                ->trueIcon('heroicon-o-moon')
                ->trueColor('purple')
                ->falseIcon('heroicon-o-sun')
                ->falseColor('gray'),

            IconColumn::make('is_active')
                ->label('Active')
                ->boolean()
                ->trueIcon('heroicon-o-check-circle')
                ->trueColor('success')
                ->falseIcon('heroicon-o-x-circle')
                ->falseColor('gray'),

            TextColumn::make('assignments_count')
                ->label('Used By')
                ->counts('scheduleAssignments')
                ->sortable()
                ->formatStateUsing(
                    fn (mixed $state): string => number_format((int) ($state ?? 0))
                ),
        ];
    }

    private static function getFilters(): array
    {
        return [
            SelectFilter::make('is_active')
                ->label('Status')
                ->options([
                    '1' => 'Active',
                    '0' => 'Inactive',
                ]),

            SelectFilter::make('is_weekend')
                ->label('Type')
                ->options([
                    '1' => 'Weekend Only',
                    '0' => 'Weekday Only',
                ]),

            Filter::make('night_shifts')
                ->label('Night Shifts Only')
                ->toggle()
                ->query(
                    fn (Builder $query): Builder => $query->where('crosses_midnight', true)
                ),

            Filter::make('time_range')
                ->label('Time Range')
                ->schema([
                    TimePicker::make('after')
                        ->label('Starts After')
                        ->seconds(false),

                    TimePicker::make('before')
                        ->label('Ends Before')
                        ->seconds(false),
                ])
                ->query(
                    function (Builder $query, array $data): Builder {
                        $after = $data['after'] ?? null;
                        $before = $data['before'] ?? null;

                        return $query
                            ->when(
                                filled($after),
                                fn (Builder $query): Builder => $query->where(
                                    'start_time',
                                    '>=',
                                    $after
                                )
                            )
                            ->when(
                                filled($before),
                                fn (Builder $query): Builder => $query->where(
                                    'end_time',
                                    '<=',
                                    $before
                                )
                            );
                    }
                ),
        ];
    }

    private static function getActions(): array
    {
        return [
            ViewAction::make(),

            EditAction::make(),

            Action::make('toggle_active')
                ->label(
                    fn (EmployeeShift $record): string => $record->is_active ? 'Deactivate' : 'Activate'
                )
                ->icon(
                    fn (EmployeeShift $record): string => $record->is_active
                        ? 'heroicon-o-x-circle'
                        : 'heroicon-o-check-circle'
                )
                ->requiresConfirmation()
                ->modalHeading(
                    fn (EmployeeShift $record): string => $record->is_active
                        ? 'Deactivate shift'
                        : 'Activate shift'
                )
                ->modalDescription(
                    fn (EmployeeShift $record): string => $record->is_active
                        ? "Are you sure you want to deactivate {$record->name}?"
                        : "Are you sure you want to activate {$record->name}?"
                )
                ->color(
                    fn (EmployeeShift $record): string => $record->is_active ? 'warning' : 'success'
                )
                ->action(
                    function (EmployeeShift $record): void {
                        $record->update([
                            'is_active' => ! $record->is_active,
                        ]);
                    }
                ),
        ];
    }

    private static function getBulkActions(): array
    {
        return [
            BulkActionGroup::make([
                DeleteBulkAction::make(),

                Action::make('bulk_deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->color('warning')
                    ->action(
                        function (Collection $records): void {
                            $records->each(
                                fn (EmployeeShift $record) => $record->update([
                                    'is_active' => false,
                                ])
                            );
                        }
                    ),
            ]),
        ];
    }

    private static function formatTime(mixed $value): string
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->format('H:i');
        }

        try {
            return Carbon::parse((string) $value)->format('H:i');
        } catch (Throwable) {
            return '—';
        }
    }

    private static function calculateDuration(
        EmployeeShift $record
    ): string {
        if (
            blank($record->start_time)
            || blank($record->end_time)
        ) {
            return '—';
        }

        try {
            $start = self::parseTime($record->start_time);
            $end = self::parseTime($record->end_time);

            if (
                $record->crosses_midnight
                || $end->lessThanOrEqualTo($start)
            ) {
                $end->addDay();
            }

            $minutes = $start->diffInMinutes($end);

            if ($minutes <= 0) {
                return '—';
            }

            return number_format($minutes / 60, 1).'h';
        } catch (Throwable) {
            return '—';
        }
    }

    private static function parseTime(mixed $value): Carbon
    {
        if ($value instanceof \DateTimeInterface) {
            return Carbon::instance($value)->setDate(2000, 1, 1);
        }

        $time = trim((string) $value);

        foreach (['H:i:s', 'H:i'] as $format) {
            try {
                return Carbon::createFromFormat(
                    $format,
                    $time
                )->setDate(2000, 1, 1);
            } catch (Throwable) {
                // Try the next supported format.
            }
        }

        return Carbon::parse($time)->setDate(2000, 1, 1);
    }
}
