<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceStaffingRequirements\Tables;

use App\Models\WorkforceStaffingRequirement;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Query\Builder;

class WorkforceStaffingRequirementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('weekday', 'asc')
            ->columns([
                TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->width('120px'),

                TextColumn::make('department.name')
                    ->label('Department')
                    ->sortable()
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('workCenter.name')
                    ->label('Work Center')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('attendanceLocation.name')
                    ->label('Location')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('employeeShift.name')
                    ->label('Shift')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                TextColumn::make('rosterRole.name')
                    ->label('Role')
                    ->sortable()
                    ->searchable()
                    ->weight('font-medium'),

                TextColumn::make('weekday')
                    ->label('Day')
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        1 => 'Mon',
                        2 => 'Tue',
                        3 => 'Wed',
                        4 => 'Thu',
                        5 => 'Fri',
                        6 => 'Sat',
                        7 => 'Sun',
                        default => 'Unknown',
                    })
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        1, 2, 3, 4, 5 => 'primary',
                        6 => 'warning',
                        7 => 'danger',
                        default => 'gray',
                    })
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('staffing_range')
                    ->label('Staffing')
                    ->getStateUsing(fn (WorkforceStaffingRequirement $record): string => "{$record->minimum_required} / {$record->target_required} / {$record->maximum_allowed}"
                    )
                    ->description('Min / Target / Max')
                    ->fontFamily('font-mono')
                    ->alignCenter(),

                TextColumn::make('effective_period')
                    ->label('Effective')
                    ->getStateUsing(fn (WorkforceStaffingRequirement $record): string => $record->effective_from->equalTo($record->effective_to ?? $record->effective_from)
                        ? $record->effective_from->format('M d, Y')
                        : "{$record->effective_from->format('M d')} – ".($record->effective_to?->format('M d, Y') ?? 'Ongoing')
                    )
                    ->sortable(['effective_from', 'effective_to'])
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('business_id')
                    ->label('Business')
                    ->relationship('business', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('work_center_id')
                    ->label('Work Center')
                    ->relationship('workCenter', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('employee_shift_id')
                    ->label('Shift')
                    ->relationship('employeeShift', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('roster_role_id')
                    ->label('Role')
                    ->relationship('rosterRole', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('weekday')
                    ->label('Weekday')
                    ->options([
                        1 => 'Monday',
                        2 => 'Tuesday',
                        3 => 'Wednesday',
                        4 => 'Thursday',
                        5 => 'Friday',
                        6 => 'Saturday',
                        7 => 'Sunday',
                    ])
                    ->multiple()
                    ->native(false),

                TernaryFilter::make('is_active')
                    ->label('Active Only')
                    ->placeholder('All records')
                    ->trueLabel('Active')
                    ->falseLabel('Inactive'),

                TernaryFilter::make('is_currently_effective')
                    ->label('Currently Effective')
                    ->placeholder('All periods')
                    ->trueLabel('Effective now')
                    ->falseLabel('Not effective')
                    ->queries(
                        true: fn (Builder $query) => $query
                            ->whereDate('effective_from', '<=', now())
                            ->where(function (Builder $query) {
                                $query->whereNull('effective_to')
                                    ->orWhereDate('effective_to', '>=', now());
                            }),
                        false: fn (Builder $query) => $query
                            ->whereDate('effective_from', '>', now())
                            ->orWhere(function (Builder $query) {
                                $query->whereNotNull('effective_to')
                                    ->whereDate('effective_to', '<', now());
                            }),
                    ),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersFormColumns(4)
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('No staffing requirements')
            ->emptyStateDescription('Define minimum, target, and maximum staffing levels for each role, shift, and weekday.')
            ->emptyStateIcon('heroicon-o-users');
    }
}
