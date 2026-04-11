<?php

namespace App\Filament\Resources\VatMasters\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;

class VatMastersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('VAT Code')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable(),

                TextColumn::make('percentage')
                    ->label('Rate')
                    ->suffix('%')
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray')
                    ->sortable()
                    ->alignCenter(),

                TextColumn::make('purchaseAccount.account_number')
                    ->label('Purchase VAT A/C')
                    ->description(fn ($record) => $record->purchaseAccount?->name)
                    ->icon('heroicon-m-arrow-down-left')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('salesAccount.account_number')
                    ->label('Sales VAT A/C')
                    ->description(fn ($record) => $record->salesAccount?->name)
                    ->icon('heroicon-m-arrow-up-right')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->filters([
                Filter::make('taxable')
                    ->query(fn ($query) => $query->where('percentage', '>', 0)),
                Filter::make('exempt')
                    ->query(fn ($query) => $query->where('percentage', '=', 0)),
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
