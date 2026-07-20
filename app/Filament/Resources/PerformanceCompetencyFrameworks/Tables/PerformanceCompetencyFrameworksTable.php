<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencyFrameworks\Tables;

use App\Models\PerformanceCompetencyFramework;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class PerformanceCompetencyFrameworksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('id')
                    ->label('#')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Framework')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->description(
                        fn (
                            PerformanceCompetencyFramework $record
                        ): ?string => filled($record->description)
                            ? $record->description
                            : null
                    )
                    ->wrap(),

                TextColumn::make('business.name')
                    ->label('Business')
                    ->searchable()
                    ->sortable()
                    ->placeholder('Global')
                    ->toggleable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->trueColor('success')
                    ->falseIcon('heroicon-o-x-circle')
                    ->falseColor('gray')
                    ->sortable(),

                TextColumn::make('effective_from')
                    ->label('Effective From')
                    ->date('M d, Y')
                    ->sortable()
                    ->placeholder('Immediately')
                    ->toggleable(),

                TextColumn::make('effective_to')
                    ->label('Effective To')
                    ->date('M d, Y')
                    ->sortable()
                    ->placeholder('No end date')
                    ->toggleable(),

                TextColumn::make('competencies_count')
                    ->label('Competencies')
                    ->counts('competencies')
                    ->badge()
                    ->color('success')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M d, Y g:i A')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->dateTime('M d, Y g:i A')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),
            ])
            ->filters([
                Filter::make('active')
                    ->label('Active Only')
                    ->toggle()
                    ->query(
                        fn (Builder $query): Builder => $query->where('is_active', true)
                    ),

                Filter::make('effective_now')
                    ->label('Currently Effective')
                    ->toggle()
                    ->query(
                        fn (Builder $query): Builder => $query
                            ->where(
                                function (
                                    Builder $query
                                ): void {
                                    $query
                                        ->whereNull(
                                            'effective_from'
                                        )
                                        ->orWhereDate(
                                            'effective_from',
                                            '<=',
                                            today()
                                        );
                                }
                            )
                            ->where(
                                function (
                                    Builder $query
                                ): void {
                                    $query
                                        ->whereNull(
                                            'effective_to'
                                        )
                                        ->orWhereDate(
                                            'effective_to',
                                            '>=',
                                            today()
                                        );
                                }
                            )
                    ),

                Filter::make('expired')
                    ->label('Expired')
                    ->toggle()
                    ->query(
                        fn (Builder $query): Builder => $query
                            ->whereNotNull('effective_to')
                            ->whereDate(
                                'effective_to',
                                '<',
                                today()
                            )
                    ),

                Filter::make('upcoming')
                    ->label('Upcoming')
                    ->toggle()
                    ->query(
                        fn (Builder $query): Builder => $query
                            ->whereNotNull('effective_from')
                            ->whereDate(
                                'effective_from',
                                '>',
                                today()
                            )
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
                                PerformanceCompetencyFramework::query()
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
                                PerformanceCompetencyFramework::query()
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
            ->emptyStateIcon(
                'heroicon-o-squares-2x2'
            )
            ->emptyStateHeading(
                'No competency frameworks found'
            )
            ->emptyStateDescription(
                'Create a competency framework to organize performance competencies.'
            );
    }
}
