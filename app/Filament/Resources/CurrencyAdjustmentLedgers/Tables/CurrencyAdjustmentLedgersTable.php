<?php

namespace App\Filament\Resources\CurrencyAdjustmentLedgers\Tables;

use App\Enums\CurrencyAdjustmentType;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CurrencyAdjustmentLedgersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('document_no')
                    ->label('Doc No.')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->document_type),
                TextColumn::make('currency.code')
                    ->label('Curr.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('adjustment_type')
                    ->badge()
                    ->sortable(),
                TextColumn::make('adjustment_amount')
                    ->label('Gain/Loss')
                    ->numeric(decimalPlaces: 2)
                    ->sortable()
                    ->weight('bold')
                    ->color(fn ($record) => $record->isGain() ? 'success' : 'danger')
                    ->alignment('right'),
                TextColumn::make('new_exch_rate')
                    ->label('Adj. Rate')
                    ->numeric(decimalPlaces: 6)
                    ->toggleable(),
                TextColumn::make('adjustmentAccount.name')
                    ->label('G/L Account')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('createdBy.name')
                    ->label('User')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Entry Date')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('posting_date', 'desc')
            ->filters([
                SelectFilter::make('currency_id')
                    ->relationship('currency', 'code')
                    ->searchable(),
                SelectFilter::make('adjustment_type')
                    ->options(CurrencyAdjustmentType::class),
                \Filament\Tables\Filters\Filter::make('gains_only')
                    ->query(fn ($query) => $query->where('adjustment_amount', '>', 0)),
                \Filament\Tables\Filters\Filter::make('losses_only')
                    ->query(fn ($query) => $query->where('adjustment_amount', '<', 0)),
            ])
            ->recordActions([
            ])
            ->toolbarActions([
            ]);
    }
}
