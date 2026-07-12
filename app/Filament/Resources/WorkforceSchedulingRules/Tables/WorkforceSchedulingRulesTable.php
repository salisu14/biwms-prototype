<?php

declare(strict_types=1);

namespace App\Filament\Resources\WorkforceSchedulingRules\Tables;

use App\Models\WorkforceSchedulingRule;
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
use Illuminate\Database\Eloquent\Builder;

class WorkforceSchedulingRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('rule_type', 'asc')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->sortable()
                    ->searchable()
                    ->weight('font-bold')
                    ->width('140px')
                    ->copyable(),

                TextColumn::make('name')
                    ->label('Rule Name')
                    ->sortable()
                    ->searchable()
                    ->weight('font-medium'),

                TextColumn::make('rule_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS => 'Min Rest Hours',
                        WorkforceSchedulingRule::TYPE_MAXIMUM_DAILY_HOURS => 'Max Daily Hours',
                        WorkforceSchedulingRule::TYPE_MAXIMUM_WEEKLY_HOURS => 'Max Weekly Hours',
                        WorkforceSchedulingRule::TYPE_MAXIMUM_CONSECUTIVE_DAYS => 'Max Consecutive Days',
                        default => $state,
                    })
                    ->colors([
                        'info' => WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS,
                        'warning' => WorkforceSchedulingRule::TYPE_MAXIMUM_DAILY_HOURS,
                        'danger' => WorkforceSchedulingRule::TYPE_MAXIMUM_WEEKLY_HOURS,
                        'primary' => WorkforceSchedulingRule::TYPE_MAXIMUM_CONSECUTIVE_DAYS,
                    ])
                    ->icons([
                        'heroicon-o-moon' => WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS,
                        'heroicon-o-sun' => WorkforceSchedulingRule::TYPE_MAXIMUM_DAILY_HOURS,
                        'heroicon-o-calendar' => WorkforceSchedulingRule::TYPE_MAXIMUM_WEEKLY_HOURS,
                        'heroicon-o-calendar-days' => WorkforceSchedulingRule::TYPE_MAXIMUM_CONSECUTIVE_DAYS,
                    ])
                    ->sortable(),

                TextColumn::make('value_display')
                    ->label('Limit')
                    ->getStateUsing(fn (WorkforceSchedulingRule $record): string => $record->value_decimal !== null
                        ? number_format((float) $record->value_decimal, 2).' hrs'
                        : ($record->value_integer !== null
                        ? $record->value_integer.' days'
                        : '—')
                    )
                    ->fontFamily('font-mono')
                    ->alignCenter()
                    ->sortable(['value_decimal', 'value_integer']),

                TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->colors([
                        'warning' => 'warning',
                        'danger' => 'error',
                        'primary' => 'critical',
                    ])
                    ->icons([
                        'heroicon-o-exclamation-triangle' => 'warning',
                        'heroicon-o-no-symbol' => 'error',
                        'heroicon-o-shield-exclamation' => 'critical',
                    ])
                    ->sortable(),

                TextColumn::make('scope')
                    ->label('Scope')
                    ->getStateUsing(fn (WorkforceSchedulingRule $record): string => collect([
                        $record->department?->name,
                        $record->workCenter?->name,
                        $record->employeeShift?->name,
                    ])->filter()->implode(' › ') ?: 'Global'
                    )
                    ->placeholder('Global')
                    ->toggleable()
                    ->limit(30)
                    ->tooltip(fn (WorkforceSchedulingRule $record): ?string => collect([
                        $record->department?->name,
                        $record->workCenter?->name,
                        $record->employeeShift?->name,
                    ])->filter()->implode(' › ') ?: null
                    ),

                TextColumn::make('effective_period')
                    ->label('Effective')
                    ->getStateUsing(fn (WorkforceSchedulingRule $record): string => $record->effective_to
                        ? "{$record->effective_from->format('M d')} – {$record->effective_to->format('M d, Y')}"
                        : $record->effective_from->format('M d, Y').' – Ongoing'
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
                    ->alignCenter(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('rule_type')
                    ->label('Rule Type')
                    ->options([
                        WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS => 'Minimum Rest Hours',
                        WorkforceSchedulingRule::TYPE_MAXIMUM_DAILY_HOURS => 'Maximum Daily Hours',
                        WorkforceSchedulingRule::TYPE_MAXIMUM_WEEKLY_HOURS => 'Maximum Weekly Hours',
                        WorkforceSchedulingRule::TYPE_MAXIMUM_CONSECUTIVE_DAYS => 'Maximum Consecutive Days',
                    ])
                    ->multiple()
                    ->native(false),

                SelectFilter::make('severity')
                    ->options([
                        'warning' => 'Warning',
                        'error' => 'Error',
                        'critical' => 'Critical',
                    ])
                    ->multiple()
                    ->native(false),

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

                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All rules')
                    ->trueLabel('Active only')
                    ->falseLabel('Inactive only'),

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
            ->emptyStateHeading('No scheduling rules')
            ->emptyStateDescription('Define rules to enforce rest periods, hour limits, and consecutive day caps.')
            ->emptyStateIcon('heroicon-o-shield-check');
    }
}
