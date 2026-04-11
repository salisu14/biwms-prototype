<?php

namespace App\Filament\Resources\Payments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('payment_number')
                    ->label('No.')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('payment_direction')
                    ->label('Direction')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'RECEIPT' => 'success',
                        'DISBURSEMENT' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('party_name')
                    ->label('Counterparty')
                    ->searchable()
                    ->description(fn ($record) => $record->party_type),
                TextColumn::make('payment_amount')
                    ->label('Amount')
                    ->money(fn ($record) => $record->currency?->code ?? $record->currency_code)
                    ->sortable()
                    ->alignment('right'),
                TextColumn::make('unapplied_amount')
                    ->label('Balance')
                    ->money(fn ($record) => $record->currency?->code ?? $record->currency_code)
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success')
                    ->weight('bold')
                    ->alignment('right'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'POSTED' => 'success',
                        'VOIDED' => 'danger',
                        'PENDING' => 'warning',
                        default => 'gray',
                    }),
                IconColumn::make('reconciled')
                    ->boolean()
                    ->alignCenter(),
                TextColumn::make('bankAccount.account_name')
                    ->label('Bank')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('payment_date', 'desc')
            ->filters([
                SelectFilter::make('payment_direction')
                    ->options([
                        'RECEIPT' => 'Receipts',
                        'DISBURSEMENT' => 'Disbursements',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'POSTED' => 'Posted',
                        'VOIDED' => 'Voided',
                    ]),
                TernaryFilter::make('reconciled')
                    ->label('Reconciliation Status'),
                SelectFilter::make('party_type')
                    ->options([
                        'CUSTOMER' => 'Customers',
                        'VENDOR' => 'Vendors',
                    ]),
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
