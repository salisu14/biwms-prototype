<?php

namespace App\Filament\Resources\PurchaseReceipts\Tables;

use App\Models\PurchaseReceipt;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
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
                    ->sortable()
                    ->description(fn ($record) => $record->vendor?->vendor_code
                        ? "{$record->vendor->vendor_code} - ".($record->vendor?->vendor_name ?? $record->buy_from_vendor_name ?? '')
                        : ''),
                TextColumn::make('purchase_order_no')
                    ->label('Order No.')
                    ->searchable(),
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('receivingLocation.name')
                    ->label('Location')
                    ->description(fn ($record) => $record->receivingLocation?->code
                        ? "{$record->receivingLocation->code} - {$record->receivingLocation->name}"
                        : ($record->location_code ?? '')),
                IconColumn::make('posted')
                    ->boolean(),
                TextColumn::make('posted')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Posted' : 'Open')
                    ->color(fn (bool $state): string => match ($state) {
                        true => 'success',
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
                Action::make('post')
                    ->label('Post Receipt')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (PurchaseReceipt $record): bool => ! $record->posted)
                    ->action(function (PurchaseReceipt $record): void {
                        try {
                            $record->post((int) auth()->id());
                            Notification::make()->title('Purchase Receipt posted successfully')->success()->send();
                        } catch (\Throwable $exception) {
                            Notification::make()->title('Unable to post receipt')->body($exception->getMessage())->danger()->send();
                        }
                    }),
                ViewAction::make(),
                EditAction::make()->visible(fn (PurchaseReceipt $record): bool => ! $record->posted),
            ]);
    }
}
