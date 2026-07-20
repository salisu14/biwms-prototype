<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceRatingScales\Tables;

use App\Models\PerformanceRatingScale;
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

class PerformanceRatingScalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name', 'asc')
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->sortable()
                    ->searchable()
                    ->weight('font-bold')
                    ->width('140px')
                    ->copyable(),

                TextColumn::make('name')
                    ->label('Scale Name')
                    ->sortable()
                    ->searchable()
                    ->weight('font-medium'),

                TextColumn::make('business.name')
                    ->label('Business')
                    ->sortable()
                    ->searchable()
                    ->toggleable()
                    ->width('140px'),

                TextColumn::make('score_range')
                    ->label('Score Range')
                    ->getStateUsing(fn (PerformanceRatingScale $record): string => number_format((float) $record->minimum_score, $record->decimal_places)
                        .' – '
                        .number_format((float) $record->maximum_score, $record->decimal_places)
                    )
                    ->fontFamily('font-mono')
                    ->alignCenter()
                    ->sortable(['minimum_score', 'maximum_score']),

                TextColumn::make('decimal_places')
                    ->label('Precision')
                    ->formatStateUsing(fn (int $state): string => "{$state} decimal".($state === 1 ? '' : 's'))
                    ->alignCenter()
                    ->toggleable()
                    ->toggledHiddenByDefault(),

                TextColumn::make('levels_count')
                    ->label('Levels')
                    ->counts('levels')
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('effective_period')
                    ->label('Effective')
                    ->getStateUsing(fn (PerformanceRatingScale $record): string => $record->effective_to
                        ? "{$record->effective_from->format('M d')} – {$record->effective_to->format('M d, Y')}"
                        : $record->effective_from->format('M d, Y').' – Ongoing'
                    )
                    ->sortable(['effective_from', 'effective_to'])
                    ->toggleable(),

                IconColumn::make('is_default')
                    ->label('Default')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus')
                    ->trueColor('warning')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->tooltip(fn (PerformanceRatingScale $record): string => $record->is_default ? 'Default scale for this business' : 'Not the default'
                    ),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),

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

                TernaryFilter::make('is_default')
                    ->label('Default Scale')
                    ->placeholder('All scales')
                    ->trueLabel('Default only')
                    ->falseLabel('Non-default only'),

                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All scales')
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
            ->emptyStateHeading('No rating scales')
            ->emptyStateDescription('Create rating scales to define score ranges for performance evaluations.')
            ->emptyStateIcon('heroicon-o-scale');
    }
}
