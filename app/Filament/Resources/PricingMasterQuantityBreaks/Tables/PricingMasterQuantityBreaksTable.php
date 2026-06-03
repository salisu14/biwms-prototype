<?php

namespace App\Filament\Resources\PricingMasterQuantityBreaks\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PricingMasterQuantityBreaksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pricingMaster.price_list_code')
                    ->label('Price List Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->description(fn ($record) => $record->pricingMaster?->description),

                TextColumn::make('tier_summary')
                    ->label('Quantity Tier')
                    ->state(fn ($record) => $record->getTierDescription())
                    ->searchable(false)
                    ->sortable(false)
                    ->badge()
                    ->color('gray')
                    ->grow(),

                TextColumn::make('minimum_quantity')
                    ->label('Min Qty')
                    ->numeric(decimalPlaces: 0)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('maximum_quantity')
                    ->label('Max Qty')
                    ->numeric(decimalPlaces: 0)
                    ->sortable()
                    ->default('Unlimited')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->money('NGN')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('discount_percent')
                    ->label('Disc. %')
                    ->numeric(decimalPlaces: 2)
                    ->suffix('%')
                    ->sortable()
                    ->alignEnd()
                    ->color('success'),

                TextColumn::make('discount_amount')
                    ->label('Disc. Amt')
                    ->money('NGN')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('unit_of_measure_code')
                    ->label('UoM')
                    ->badge()
                    ->toggleable(),

                TextColumn::make('line_number')
                    ->label('Order')
                    ->numeric()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('pricing_master_id')
                    ->label('Pricing Master')
                    ->relationship('pricingMaster', 'price_list_code')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('line_number', 'asc') // Crucial for quantity breaks so they evaluate in order!
            ->emptyStateHeading('No quantity breaks')
            ->emptyStateDescription('Define quantity tiers to apply bulk discounts or special pricing.')
            ->emptyStateIcon('heroicon-o-scale');
    }
}
