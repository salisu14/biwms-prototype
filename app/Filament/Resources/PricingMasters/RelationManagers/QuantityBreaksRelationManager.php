<?php

namespace App\Filament\Resources\PricingMasters\RelationManagers;

use App\Filament\Resources\PricingMasterQuantityBreaks\PricingMasterQuantityBreakResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class QuantityBreaksRelationManager extends RelationManager
{
    protected static string $relationship = 'quantityBreaks';

    protected static ?string $relatedResource = PricingMasterQuantityBreakResource::class;

    protected static ?string $title = 'Quantity Breaks';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('line_number')
                    ->label('Line No.')
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('minimum_quantity')
                    ->label('Min Qty')
                    ->numeric(decimalPlaces: 0),

                TextColumn::make('maximum_quantity')
                    ->label('Max Qty')
                    ->numeric(decimalPlaces: 0)
                    ->default('Unlimited'),

                TextColumn::make('unit_price')
                    ->label('Unit Price')
                    ->formatStateUsing(function ($state, $record): string {
                        if ($state === null) {
                            return '-';
                        }

                        return Number::currency((float) $state, $record->pricingMaster?->currency_code ?? config('app.default_currency', 'USD'));
                    })
                    ->alignEnd(),

                TextColumn::make('discount_percent')
                    ->label('Disc. %')
                    ->suffix('%')
                    ->alignEnd(),

                TextColumn::make('discount_amount')
                    ->label('Disc. Amt')
                    ->formatStateUsing(function ($state, $record): string {
                        if ($state === null) {
                            return '-';
                        }

                        return Number::currency((float) $state, $record->pricingMaster?->currency_code ?? config('app.default_currency', 'USD'));
                    })
                    ->alignEnd(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
