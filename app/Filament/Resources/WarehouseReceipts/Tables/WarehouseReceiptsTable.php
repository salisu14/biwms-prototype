<?php

namespace App\Filament\Resources\WarehouseReceipts\Tables;

use App\Enums\WarehouseReceiptStatus;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WarehouseReceiptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('Receipt No.')
                    ->weight('bold')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (WarehouseReceiptStatus $state): string => match ($state) {
                        WarehouseReceiptStatus::OPEN => 'gray',
                        WarehouseReceiptStatus::RELEASED => 'info',
                        WarehouseReceiptStatus::PARTIALLY_RECEIVED => 'warning',
                        WarehouseReceiptStatus::RECEIVED => 'success',
                    }),

                TextColumn::make('vendor.vendor_name')
                    ->label('Vendor')
                    ->description(fn ($record) => "Ref: {$record->source_document_number}")
                    ->searchable()
                    ->sortable(),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('receipt_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('expected_receipt_date')
                    ->date()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('posted_date')
                    ->label('Posted At')
                    ->dateTime()
                    ->toggleable(),

                TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'OPEN' => 'Open',
                        'RELEASED' => 'Released',
                        'PARTIALLY_RECEIVED' => 'Partially Received',
                        'RECEIVED' => 'Received',
                    ]),
                SelectFilter::make('location_id')
                    ->relationship('location', 'name'),
                SelectFilter::make('vendor_id')
                    ->relationship('vendor', 'vendor_name'),
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
