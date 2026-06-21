<?php

namespace App\Filament\Resources\VendorLedgerEntries\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class VendorLedgerEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_number')
                    ->label('Entry#')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),

                TextColumn::make('vendor.vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->description(fn ($record) => $record->vendor?->vendor_code),

                TextColumn::make('document_type')
                    ->label('Type')
                    ->searchable()
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PURCHASE_INVOICE' => 'danger',
                        'PURCHASE_CREDIT_MEMO' => 'success',
                        'PAYMENT', 'BANK_TRANSFER' => 'info',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str_replace('PURCHASE_', '', $state)),

                TextColumn::make('document_number')
                    ->label('Doc No.')
                    ->searchable()
                    ->copyable(),

                TextColumn::make('posting_date')
                    ->label('Posting Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record->open && $record->due_date && $record->due_date < now() ? 'danger' : 'gray'),

                TextColumn::make('debit_amount')
                    ->label('Debit')
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.($record->currency_code ?? config('app.default_currency', 'NGN')))
                    ->sortable()
                    ->alignEnd()
                    ->color('danger'),
//                    ->visible(fn ($record) => $record?->debit_amount > 0),

                TextColumn::make('credit_amount')
                    ->label('Credit')
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.($record->currency_code ?? config('app.default_currency', 'NGN')))
                    ->sortable()
                    ->alignEnd()
                    ->color('success'),
//                    ->visible(fn ($record) => $record?->credit_amount > 0),

                TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->formatStateUsing(fn ($state, $record) => number_format((float) $state, 2).' '.($record->currency_code ?? config('app.default_currency', 'NGN')))
                    ->sortable()
                    ->alignEnd()
                    ->weight('bold')
                    ->color(fn ($record) => $record->open ? 'warning' : 'gray'),

                TextColumn::make('aging_category')
                    ->label('Aging')
                    ->state(fn ($record) => $record->aging_category)
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'CURRENT' => 'success',
                        '1-30' => 'info',
                        '31-60' => 'warning',
                        '61-90' => 'danger',
                        'OVER_90' => 'danger',
                        default => 'gray',
                    })
                    ->toggleable(),

                IconColumn::make('open')
                    ->label('Open')
                    ->boolean()
                    ->toggleable(),

                IconColumn::make('reversed')
                    ->label('Reversed')
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->trueColor('danger')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('open')
                    ->label('Entry Status')
                    ->trueLabel('Open Entries')
                    ->falseLabel('Closed Entries')
                    ->queries(
                        true: fn ($query) => $query->open(),
                        false: fn ($query) => $query->where('open', false),
                    ),

                TernaryFilter::make('overdue')
                    ->label('Overdue Status')
                    ->trueLabel('Overdue Only')
                    ->falseLabel('Not Overdue')
                    ->queries(
                        true: fn ($query) => $query->overdue(),
                        false: fn ($query) => $query->where(fn ($q) => $q->where('due_date', '>=', now())->orWhereNull('due_date')),
                    ),

                SelectFilter::make('document_type')
                    ->options([
                        'PURCHASE_INVOICE' => 'Purchase Invoice',
                        'PAYMENT' => 'Payment',
                        'BANK_TRANSFER' => 'Bank Transfer',
                        'PURCHASE_CREDIT_MEMO' => 'Credit Memo',
                    ]),

                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'vendor_name')
                    ->searchable()
                    ->preload(),

                TernaryFilter::make('reversed')
                    ->label('Reversed')
                    ->trueLabel('Reversed Only')
                    ->falseLabel('Not Reversed')
                    ->queries(
                        true: fn ($query) => $query->where('reversed', true),
                        false: fn ($query) => $query->notReversed(),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->defaultSort('entry_number', 'desc');
    }
}
