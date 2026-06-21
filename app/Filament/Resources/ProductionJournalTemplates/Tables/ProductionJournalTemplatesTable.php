<?php

namespace App\Filament\Resources\ProductionJournalTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductionJournalTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('journal_type')
                    ->label('Type')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('flushing_method_filter')
                    ->label('Flushing')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),

                TextColumn::make('batches_count')
                    ->label('Active Batches')
                    ->counts('batches')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('auto_post_output')
                    ->label('Auto-Output')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('journal_type')
                    ->options([
                        'consumption' => 'Consumption',
                        'output' => 'Output',
                        'capacity' => 'Capacity',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active Templates'),

                SelectFilter::make('flushing_method_filter')
                    ->options([
                        'manual' => 'Manual',
                        'forward' => 'Forward',
                        'backward' => 'Backward',
                        'all' => 'All',
                    ]),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])->defaultSort('name', 'asc');
    }
}
