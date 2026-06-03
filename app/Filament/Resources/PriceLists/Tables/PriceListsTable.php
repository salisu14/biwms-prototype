<?php

namespace App\Filament\Resources\PriceLists\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class PriceListsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                IconColumn::make('status')
                    ->label('Status')
                    ->state(function ($record) {
                        $started = $record->starting_date <= now();
                        $expired = $record->ending_date && $record->ending_date < now();

                        if ($expired) return 'expired';
                        if ($started) return 'active';
                        return 'scheduled';
                    })
                    ->icon(fn (string $state): string => match ($state) {
                        'active' => 'heroicon-o-check-circle',
                        'scheduled' => 'heroicon-o-clock',
                        'expired' => 'heroicon-o-x-circle',
                        default => 'heroicon-o-question-mark-circle',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'active' => 'success',
                        'scheduled' => 'info',
                        'expired' => 'danger',
                        default => 'gray',
                    })
                    ->tooltip(fn (string $state): string => ucfirst($state)),

                TextColumn::make('item.item_code')
                    ->label('Item Code')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('item.description')
                    ->label('Item Description')
                    ->searchable()
                    ->limit(30)
                    ->toggleable()
                    ->tooltip(fn ($record) => $record->item?->description),

                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->default('—'),

                TextColumn::make('customerGroup.name')
                    ->label('Customer Group')
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->default('—'),

                TextColumn::make('price')
                    ->label('Price')
                    ->alignEnd()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->formatStateUsing(fn ($record) => Number::currency($record->price, $record->currency)),

                TextColumn::make('starting_date')
                    ->label('Starts')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('ending_date')
                    ->label('Ends')
                    ->date('d/m/Y')
                    ->sortable()
                    ->default('No End')
                    ->color(fn ($record) => $record->ending_date && $record->ending_date < now() ? 'danger' : 'gray'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('active')
                    ->label('Currently Active')
                    ->placeholder('All Prices')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive/Expired')
                    ->queries(
                        true: fn ($query) => $query->where('starting_date', '<=', now())
                            ->where(function ($q) {
                                $q->whereNull('ending_date')->orWhere('ending_date', '>=', now());
                            }),
                        false: fn ($query) => $query->where('starting_date', '>', now())
                            ->orWhere(function ($q) {
                                $q->whereNotNull('ending_date')->where('ending_date', '<', now());
                            }),
                    ),

                SelectFilter::make('item_id')
                    ->label('Item')
                    ->relationship('item', 'item_code')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('customer_id')
                    ->label('Customer')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('customer_group_id')
                    ->label('Customer Group')
                    ->relationship('customerGroup', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('currency')
                    ->options(['NGN' => 'NGN', 'USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP']),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('starting_date', 'desc')
            ->emptyStateHeading('No price list entries')
            ->emptyStateDescription('Create special pricing rules for your finished goods.')
            ->emptyStateIcon('heroicon-o-currency-dollar');
    }
}
