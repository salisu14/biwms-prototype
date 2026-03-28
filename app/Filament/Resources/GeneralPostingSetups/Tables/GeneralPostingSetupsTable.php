<?php

namespace App\Filament\Resources\GeneralPostingSetups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class GeneralPostingSetupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable(),
                TextColumn::make('sales_account')
                    ->searchable(),
                TextColumn::make('sales_credit_account')
                    ->searchable(),
                TextColumn::make('sales_discount_account')
                    ->searchable(),
                TextColumn::make('purchase_account')
                    ->searchable(),
                TextColumn::make('purchase_credit_account')
                    ->searchable(),
                TextColumn::make('purchase_discount_account')
                    ->searchable(),
                TextColumn::make('cogs_account')
                    ->searchable(),
                TextColumn::make('purchase_variance_account')
                    ->searchable(),
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
