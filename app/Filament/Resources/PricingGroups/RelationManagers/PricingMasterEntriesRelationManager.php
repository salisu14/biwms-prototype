<?php

namespace App\Filament\Resources\PricingGroups\RelationManagers;

use App\Filament\Resources\PricingMasters\PricingMasterResource;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class PricingMasterEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'pricingMasterEntries';

    protected static ?string $relatedResource = PricingMasterResource::class;

    protected static ?string $title = 'Pricing Masters';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('price_list_code')
                    ->label('Entry')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold')
                    ->description(fn ($record) => $record->description),

                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->description(fn ($record) => $record->item?->description)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('scope')
                    ->label('Scope')
                    ->state(function ($record): string {
                        return match ($record->price_list_type) {
                            'CUSTOMER' => $record->customer
                                ? "{$record->customer->customer_number} - {$record->customer->name}"
                                : 'Specific Customer',
                            'CUSTOMER_GROUP', 'CAMPAIGN' => $record->pricingGroup
                                ? "{$record->pricingGroup->code} - {$record->pricingGroup->name}"
                                : 'Pricing Group',
                            'TRANSFER' => 'Transfer',
                            default => 'All Customers',
                        };
                    })
                    ->badge()
                    ->color('gray'),

                TextColumn::make('price_type')
                    ->label('Method')
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'UNIT_PRICE' => 'Fixed Unit Price',
                        'PERCENT_DISCOUNT' => 'Discount %',
                        'AMOUNT_DISCOUNT' => 'Discount Amount',
                        'COST_PLUS_PERCENT' => 'Cost + %',
                        'COST_PLUS_AMOUNT' => 'Cost + Amount',
                        'FORMULA' => 'Formula',
                        default => str_replace('_', ' ', $state),
                    }),

                TextColumn::make('price_value')
                    ->label('Price / Disc.')
                    ->alignEnd()
                    ->formatStateUsing(function ($record): string {
                        if ($record->price_type === 'UNIT_PRICE' && $record->unit_price !== null) {
                            return Number::currency((float) $record->unit_price, $record->currency_code);
                        }

                        if ($record->price_type === 'PERCENT_DISCOUNT' && $record->discount_percent !== null) {
                            return $record->discount_percent.'%';
                        }

                        if ($record->price_type === 'AMOUNT_DISCOUNT' && $record->discount_amount !== null) {
                            return '-'.Number::currency((float) $record->discount_amount, $record->currency_code);
                        }

                        return '-';
                    }),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ACTIVE' => 'success',
                        'PENDING_APPROVAL' => 'warning',
                        'EXPIRED' => 'danger',
                        'CANCELLED' => 'danger',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('start_date')
                    ->label('Starts')
                    ->date('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('end_date')
                    ->label('Ends')
                    ->date('d/m/Y')
                    ->sortable()
                    ->default('Perpetual')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
