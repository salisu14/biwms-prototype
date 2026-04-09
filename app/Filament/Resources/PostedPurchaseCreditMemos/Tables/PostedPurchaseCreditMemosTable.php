<?php

namespace App\Filament\Resources\PostedPurchaseCreditMemos\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

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
                    ->sortable(),
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('grand_total')
                    ->money(fn ($record) => $record->currency_code)
                    ->sortable()
                    ->alignment('right'),
                IconColumn::make('posted')
                    ->boolean()
                    ->label('Posted'),
                TextColumn::make('reason_code')
                    ->toggleable(),
                TextColumn::make('location_code')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('posted'),
                SelectFilter::make('vendor_id')
                    ->relationship('vendor', 'vendor_name')
                    ->searchable(),
                SelectFilter::make('location_code'),
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
