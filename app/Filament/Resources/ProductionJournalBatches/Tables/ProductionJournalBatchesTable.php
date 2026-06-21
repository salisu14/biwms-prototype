<?php

namespace App\Filament\Resources\ProductionJournalBatches\Tables;

use App\Enums\JournalBatchStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProductionJournalBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template.name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                TextColumn::make('name')
                    ->label('Batch')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('productionOrder.no')
                    ->label('Order No.')
                    ->searchable()
                    ->placeholder('N/A'),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('lines_count')
                    ->label('Entries')
                    ->counts('lines')
                    ->badge()
                    ->color('info'),

                TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->toggleable(),

                IconColumn::make('auto_post_on_release')
                    ->label('Auto-Post')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Last Activity')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('template_id')
                    ->relationship('template', 'name'),

                SelectFilter::make('status')
                    ->options(JournalBatchStatus::class),

                SelectFilter::make('production_order_id')
                    ->relationship('productionOrder', 'document_number'),
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
