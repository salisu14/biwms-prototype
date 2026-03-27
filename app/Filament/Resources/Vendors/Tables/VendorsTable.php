<?php

namespace App\Filament\Resources\Vendors\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VendorsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vendor_code')
                    ->searchable(),
                TextColumn::make('vendor_name')
                    ->searchable(),
                TextColumn::make('contact_person')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('phone')
                    ->searchable(),
                TextColumn::make('mobile')
                    ->searchable(),
                TextColumn::make('city')
                    ->searchable(),
                TextColumn::make('state')
                    ->searchable(),
                TextColumn::make('postal_code')
                    ->searchable(),
                TextColumn::make('country')
                    ->searchable(),
                TextColumn::make('tax_id')
                    ->searchable(),
                TextColumn::make('payment_terms')
                    ->searchable(),
                TextColumn::make('currency')
                    ->searchable(),
                TextColumn::make('lead_time_days')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('minimum_order_amount')
                    ->numeric()
                    ->sortable(),
                IconColumn::make('is_active')
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
