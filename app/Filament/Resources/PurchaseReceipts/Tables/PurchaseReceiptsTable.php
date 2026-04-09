<?php

namespace App\Filament\Resources\PurchaseReceipts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PurchaseReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('Receipt No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('buy_from_vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('purchase_order_no')
                    ->label('Order No.')
                    ->searchable(),
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('receivingLocation.name')
                    ->label('Location'),
                IconColumn::make('posted')
                    ->boolean(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'POSTED' => 'success',
                        'PENDING' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('actual_receipt_date')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('posted'),
                SelectFilter::make('vendor_id')
                    ->relationship('vendor', 'vendor_name')
                    ->searchable(),
                SelectFilter::make('receiving_location_id')
                    ->relationship('receivingLocation', 'name'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
