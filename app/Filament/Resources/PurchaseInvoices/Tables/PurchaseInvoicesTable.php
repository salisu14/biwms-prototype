<?php

namespace App\Filament\Resources\PurchaseInvoices\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PurchaseInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('Invoice No.')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vendor_name')
                    ->label('Vendor')
                    ->searchable(),
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('due_date')
                    ->date()
                    ->sortable()
                    ->color(fn ($record) => $record->is_overdue ? 'danger' : null),
                TextColumn::make('grand_total')
                    ->money(fn ($record) => $record->currency_code)
                    ->sortable()
                    ->alignment('right'),
                TextColumn::make('remaining_amount')
                    ->money(fn ($record) => $record->currency_code)
                    ->label('Balance')
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->alignment('right'),
                IconColumn::make('paid_in_full')
                    ->boolean()
                    ->label('Paid'),
                IconColumn::make('cancelled')
                    ->boolean()
                    ->label('Cancelled')
                    ->trueColor('danger')
                    ->toggleable(),
                TextColumn::make('location.name')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('posted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('paid_in_full')
                    ->label('Paid Status'),
                TernaryFilter::make('cancelled'),
                SelectFilter::make('vendor_id')
                    ->relationship('vendor', 'vendor_name')
                    ->searchable(),
                SelectFilter::make('location_id')
                    ->relationship('location', 'name'),
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
