<?php

namespace App\Filament\Resources\ItemLedgers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Database\Eloquent\Builder;

class ItemLedgersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->sortable()
                    ->tooltip('Transaction Date'),

                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record): string => $record->item?->description ?? '')
                    ->tooltip(fn ($record): string => $record->item?->description ?? ''),

                TextColumn::make('location.location_name')
                    ->label('Location')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('gray'),

                // FIXED: Use TextColumn with badge() instead of BadgeColumn
                TextColumn::make('entry_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state): string => match($state) {
                        'RECEIPT', 'TRANSFER_IN', 'RETURN', 'ADJUSTMENT_POS', 'PRODUCTION_OUTPUT' => 'success',
                        'ISSUE', 'TRANSFER_OUT', 'SALE', 'ADJUSTMENT_NEG', 'SCRAP' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => str_replace('_', ' ', $state)),

                // FIXED: Use getStateUsing instead of computed property
                TextColumn::make('signed_quantity')
                    ->label('Quantity')
                    ->sortable()
                    ->weight('bold')
                    ->getStateUsing(fn ($record): float =>
                    $record->is_inbound ? $record->quantity : -$record->quantity  // Use property
                    )
                    ->color(fn ($record): string => $record->is_inbound ? 'success' : 'danger')  // Use property
                    ->formatStateUsing(fn ($record): string =>
                        ($record->is_inbound ? '+' : '-') . number_format($record->quantity, 4)
                    )
                    ->suffix(fn ($record): string => ' ' . ($record->uom?->uom_code ?? '')),

                TextColumn::make('unit_cost')
                    ->label('Unit Cost')
                    ->money('USD')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                // FIXED: Use getStateUsing for computed value
                TextColumn::make('cost_amount')
                    ->label('Total Value')
                    ->money('USD')
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw('(quantity * unit_cost) ' . $direction);
                    })
                    ->getStateUsing(fn ($record): float => $record->quantity * $record->unit_cost)
                    ->toggleable(),

                // FIXED: Use getStateUsing - balance_after should be stored or calculated
                TextColumn::make('balance_after')
                    ->label('Balance')
                    ->numeric()
                    ->sortable()
                    ->getStateUsing(fn ($record): float => $record->balance_after ?? 0)
                    ->toggleable(),

                TextColumn::make('lot_number')
                    ->label('Lot')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('expiry_date')
                    ->label('Expiry')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('creator.name')
                    ->label('Created By')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('item_id')
                    ->label('Item')
                    ->relationship('item', 'item_code')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('location_id')
                    ->label('Location')
                    ->relationship('location', 'location_name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('entry_type')
                    ->label('Entry Type')
                    ->options([
                        'RECEIPT' => 'Receipt',
                        'TRANSFER_IN' => 'Transfer In',
                        'ISSUE' => 'Issue',
                        'TRANSFER_OUT' => 'Transfer Out',
                        'SALE' => 'Sale',
                        'ADJUSTMENT_POS' => 'Positive Adjustment',
                        'ADJUSTMENT_NEG' => 'Negative Adjustment',
                        'RETURN' => 'Return',
                        'PRODUCTION_OUTPUT' => 'Production Output',
                        'SCRAP' => 'Scrap',
                    ])
                    ->multiple(),

                TernaryFilter::make('is_inbound')
                    ->label('Direction')
                    ->placeholder('All Transactions')
                    ->trueLabel('Inbound Only')
                    ->falseLabel('Outbound Only')
                    ->queries(
                        true: fn (Builder $query) => $query->whereIn('entry_type', [
                            'RECEIPT', 'TRANSFER_IN', 'RETURN', 'ADJUSTMENT_POS', 'PRODUCTION_OUTPUT'
                        ]),
                        false: fn (Builder $query) => $query->whereIn('entry_type', [
                            'ISSUE', 'TRANSFER_OUT', 'SALE', 'ADJUSTMENT_NEG', 'SCRAP'
                        ]),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
