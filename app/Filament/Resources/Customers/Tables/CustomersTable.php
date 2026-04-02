<?php

namespace App\Filament\Resources\Customers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CustomersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_number')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('generalBusinessPostingGroup.id')
                    ->searchable(),
                TextColumn::make('customerPostingGroup.id')
                    ->searchable(),
                TextColumn::make('vat_bus_posting_group')
                    ->searchable(),
                TextColumn::make('location.name')
                    ->searchable(),
                TextColumn::make('shipping_agent_code')
                    ->searchable(),
                TextColumn::make('payment_terms_code')
                    ->searchable(),
                TextColumn::make('credit_limit')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('blocked')
                    ->boolean(),
                TextColumn::make('blocked_reason')
                    ->searchable(),
                TextColumn::make('pricing_group_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('price_list_code')
                    ->searchable(),
                IconColumn::make('allow_discounts')
                    ->boolean(),
                TextColumn::make('maximum_discount_percent')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('price_includes_vat')
                    ->boolean(),
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
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
