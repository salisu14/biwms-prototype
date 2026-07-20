<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencies\Tables;

use App\Models\PerformanceCompetency;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PerformanceCompetenciesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('framework.name')
                    ->label('Framework')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->placeholder('No framework'),

                TextColumn::make('parent.name')
                    ->label('Parent Competency')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Top Level')
                    ->toggleable(),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Competency')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->description(
                        fn (
                            PerformanceCompetency $record
                        ): ?string => filled($record->description)
                            ? $record->description
                            : null
                    ),

                TextColumn::make('competency_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(
                        fn (?string $state): string => filled($state)
                            ? str($state)
                                ->replace('_', ' ')
                                ->title()
                                ->toString()
                            : 'Not Set'
                    )
                    ->color(
                        fn (?string $state): string => match ($state) {
                            'technical' => 'primary',
                            'behavioral' => 'warning',
                            'leadership' => 'success',
                            'communication' => 'info',
                            'analytical' => 'gray',
                            'custom' => 'gray',
                            default => 'gray',
                        }
                    )
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('levels_count')
                    ->label('Levels')
                    ->counts('levels')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    )
                    ->alignCenter(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),
            ])
            ->filters([
                SelectFilter::make(
                    'performance_competency_framework_id'
                )
                    ->label('Framework')
                    ->relationship(
                        name: 'framework',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (
                            Builder $query
                        ): Builder => $query->orderBy('name')
                    )
                    ->searchable()
                    ->preload(),

                SelectFilter::make('competency_type')
                    ->label('Competency Type')
                    ->options([
                        'technical' => 'Technical',
                        'behavioral' => 'Behavioral',
                        'leadership' => 'Leadership',
                        'communication' => 'Communication',
                        'analytical' => 'Analytical',
                        'custom' => 'Custom',
                    ]),

                Filter::make('active')
                    ->label('Active Only')
                    ->toggle()
                    ->query(
                        fn (Builder $query): Builder => $query->where('is_active', true)
                    ),
            ])
            ->recordActions([
                ViewAction::make(),

                EditAction::make(),

                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),

                    BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(
                            function (
                                Collection $records
                            ): void {
                                PerformanceCompetency::query()
                                    ->whereKey(
                                        $records->modelKeys()
                                    )
                                    ->update([
                                        'is_active' => true,
                                    ]);
                            }
                        )
                        ->deselectRecordsAfterCompletion(),

                    BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(
                            function (
                                Collection $records
                            ): void {
                                PerformanceCompetency::query()
                                    ->whereKey(
                                        $records->modelKeys()
                                    )
                                    ->update([
                                        'is_active' => false,
                                    ]);
                            }
                        )
                        ->deselectRecordsAfterCompletion(),
                ]),
            ])
            ->defaultSort('sort_order', 'asc')
            ->reorderable('sort_order')
            ->emptyStateHeading('No competencies found')
            ->emptyStateDescription(
                'Create competencies within a competency framework.'
            );
    }
}
