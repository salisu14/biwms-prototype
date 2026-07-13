<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalTemplates\Tables;

use App\Models\PerformanceAppraisalTemplate;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
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

class PerformanceAppraisalTemplatesTable
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
                    ->label('Template Name')
                    ->sortable()
                    ->searchable()
                    ->weight('font-medium'),

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
                    ->toggleable()
                    ->placeholder('All'),

                TextColumn::make('scale.name')
                    ->label('Rating Scale')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary')
                    ->toggleable(),

                TextColumn::make('weight_breakdown')
                    ->label('Weights')
                    ->getStateUsing(fn (PerformanceAppraisalTemplate $record): string =>
                        "G: " . number_format((float) $record->goal_weight_percent, 0)
                        . "% | C: " . number_format((float) $record->competency_weight_percent, 0)
                        . "% | O: " . number_format((float) $record->other_weight_percent, 0) . "%"
                    )
                    ->fontFamily('font-mono')
                    ->alignCenter()
                    ->toggleable()
                    ->tooltip('Goal / Competency / Other'),

                TextColumn::make('version')
                    ->label('Ver')
                    ->alignCenter()
                    ->sortable()
                    ->width('60px')
                    ->suffix('v'),

                TextColumn::make('effective_period')
                    ->label('Effective')
                    ->getStateUsing(fn (PerformanceAppraisalTemplate $record): string =>
                    $record->effective_to
                        ? "{$record->effective_from->format('M d')} – {$record->effective_to->format('M d, Y')}"
                        : $record->effective_from->format('M d, Y') . ' – Ongoing'
                    )
                    ->sortable(['effective_from', 'effective_to'])
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),

                TextColumn::make('sections_count')
                    ->label('Sections')
                    ->counts('sections')
                    ->alignCenter()
                    ->badge()
                    ->color('info')
                    ->sortable(),

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

                SelectFilter::make('applicable_department_id')
                    ->label('Department')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                SelectFilter::make('rating_scale_id')
                    ->label('Rating Scale')
                    ->relationship('scale', 'name')
                    ->searchable()
                    ->preload()
                    ->native(false),

                TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All templates')
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
            ->emptyStateHeading('No appraisal templates')
            ->emptyStateDescription('Create templates to standardize performance evaluation forms across your organization.')
            ->emptyStateIcon('heroicon-o-clipboard-document-check')
            ->emptyStateActions([
                CreateAction::make()
                    ->label('Create Appraisal Cycle'),
            ]);
    }
}
