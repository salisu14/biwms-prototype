<?php

namespace App\Filament\Resources\PricingMasterQuantityBreaks\Tables;

use App\Filament\Resources\PricingMasters\PricingMasterResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class PricingMasterQuantityBreaksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('pricingMaster.price_list_code')
                    ->label('Pricing Master')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable()
                    ->formatStateUsing(fn ($state, $record): string => $record->pricingMaster
                        ? "{$record->pricingMaster->price_list_code} - {$record->pricingMaster->description}"
                        : '-')
                    ->url(fn ($record): ?string => $record->pricingMaster
                        ? PricingMasterResource::getUrl('view', ['record' => $record->pricingMaster])
                        : null)
                    ->description(fn ($record) => $record->pricingMaster?->description),

                TextColumn::make('tier_summary')
                    ->label('Quantity Tier')
                    ->state(fn ($record) => $record->getTierDescription($record->pricingMaster?->currency_code))
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
                    ->sortable()
                    ->formatStateUsing(function ($state, $record): string {
                        if ($state === null) {
                            return '-';
                        }

                        return Number::currency((float) $state, $record->pricingMaster?->currency_code ?? config('app.default_currency', 'USD'));
                    })
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
                    ->sortable()
                    ->formatStateUsing(function ($state, $record): string {
                        if ($state === null) {
                            return '-';
                        }

                        return Number::currency((float) $state, $record->pricingMaster?->currency_code ?? config('app.default_currency', 'USD'));
                    })
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
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Delete Selected'),
                ]),
            ])
            ->defaultSort('line_number', 'asc')
            ->emptyStateHeading('No quantity breaks')
            ->emptyStateDescription('Define quantity tiers to apply bulk discounts or special pricing.')
            ->emptyStateIcon('heroicon-o-scale');
    }
}
