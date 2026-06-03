<?php

namespace App\Filament\Resources\CustomerPriceOverrides\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CustomerPriceOverridesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->customer?->no ?? '')
                    ->icon('heroicon-o-user-circle'),

                TextColumn::make('item.item_code')
                    ->label('Item No.')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Item No. copied!')
                    ->weight('bold'),

                TextColumn::make('item.description')
                    ->label('Item Description')
                    ->searchable()
                    ->limit(35)
                    ->tooltip(fn ($record) => $record->item?->description)
                    ->toggleable(),

                TextColumn::make('item.unit_price')
                    ->label('Base Price')
                    ->money('NGN')
                    ->sortable()
                    ->color('gray')
//                    ->strikeThrough(fn ($record) => $record->override_price != $record->item?->unit_price)
                    ->toggleable(),

                TextColumn::make('override_price')
                    ->label('Override Price')
                    ->money('NGN')
                    ->sortable()
                    ->weight('bold')
                    ->color(function ($record) {
                        // Visual cue: green if cheaper than base, red if more expensive
                        if (! $record->item?->unit_price) return 'primary';
                        return $record->override_price < $record->item->unit_price ? 'success' : 'danger';
                    })
                    ->icon(function ($record) {
                        if (! $record->item?->unit_price) return null;
                        return $record->override_price < $record->item->unit_price
                            ? 'heroicon-o-arrow-trending-down'
                            : 'heroicon-o-arrow-trending-up';
                    }),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('item_code')
                    ->label('Item')
                    ->relationship('item', 'description')
                    ->searchable()
                    ->preload()
                    ->getOptionLabelFromRecordUsing(
                        fn (\App\Models\Item $record) => "{$record->no} — {$record->description}"
                    ),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('No price overrides')
            ->emptyStateDescription('Create a price override to set special pricing for specific customers and items.')
            ->emptyStateIcon('heroicon-o-currency-dollar');
    }
}
