<?php

namespace App\Filament\Resources\ItemLots\Tables;

use App\Filament\Resources\Items\ItemResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemLotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->formatStateUsing(fn ($state, $record): string => $record->item
                        ? "{$record->item->item_code} - {$record->item->description}"
                        : '—')
                    ->url(fn ($record): ?string => $record->item
                        ? ItemResource::getUrl('view', ['record' => $record->item])
                        : null)
                    ->description(fn ($record): string => $record->item?->description ?? ''),

                TextColumn::make('lot_number')
                    ->label('Lot No')
                    ->searchable(),
                TextColumn::make('supplier_lot')
                    ->label('Supplier Lot')
                    ->searchable(),
                TextColumn::make('receipt_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('expiry_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('retest_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('quantity_received')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('quantity_remaining')
                    ->numeric()
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->searchable()
                    ->color(fn (string $state): string => match ($state) {
                        'APPROVED' => 'success',
                        'QUARANTINE' => 'warning',
                        'REJECTED', 'EXPIRED' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('coa_reference')
                    ->label('COA Reference')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Delete Selected'),
                ]),
            ]);
    }
}
