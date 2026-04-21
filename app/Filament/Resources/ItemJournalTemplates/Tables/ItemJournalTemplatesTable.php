<?php

namespace App\Filament\Resources\ItemJournalTemplates\Tables;

use App\Enums\JournalLineType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ItemJournalTemplatesTable
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

                TextColumn::make('default_entry_type')
                    ->label('Entry Type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('numberSeries.code')
                    ->label('No. Series')
                    ->sortable(),

                TextColumn::make('defaultInventoryAccount.account_number')
                    ->label('Default G/L')
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('item_tracking_mandatory')
                    ->label('Tracking')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('warehouse_location_mandatory')
                    ->label('Loc. Req')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('batches_count')
                    ->label('Batches')
                    ->counts('batches')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                SelectFilter::make('default_entry_type')
                    ->options(JournalLineType::class),

                TernaryFilter::make('is_active')
                    ->label('Active Status'),

                TernaryFilter::make('item_tracking_mandatory')
                    ->label('Mandatory Tracking'),
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
