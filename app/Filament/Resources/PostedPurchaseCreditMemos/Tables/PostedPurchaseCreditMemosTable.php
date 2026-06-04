<?php

namespace App\Filament\Resources\PostedPurchaseCreditMemos\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class PostedPurchaseCreditMemosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('Doc No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->vendor?->vendor_code ?? ''),
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->formatStateUsing(fn ($state, $record) => Number::currency((float) $state, $record->currency_code ?: config('app.default_currency', 'USD')))
                    ->sortable()
                    ->alignment('right'),
                IconColumn::make('posted')
                    ->boolean()
                    ->label('Posted'),
                TextColumn::make('reason_code')
                    ->label('Reason')
                    ->toggleable(),
                TextColumn::make('location_code')
                    ->label('Location')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('posted')
                    ->label('Posted Status'),
                SelectFilter::make('vendor_id')
                    ->relationship('vendor', 'vendor_name')
                    ->searchable(),
                SelectFilter::make('location_code'),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
