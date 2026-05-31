<?php

namespace App\Filament\Resources\CustomerLedgerEntries\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerLedgerEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('posting_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),
                TextColumn::make('customer.name')
                    ->label('Customer')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('document_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('document_number')
                    ->searchable(),
                TextColumn::make('description')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('debit_amount')
                    ->label('Debit')
                    ->money('NGN')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total Dr')),
                TextColumn::make('credit_amount')
                    ->label('Credit')
                    ->money('NGN')
                    ->sortable()
                    ->summarize(Sum::make()->label('Total Cr')),
                TextColumn::make('running_balance')
                    ->label('Balance')
                    ->money('NGN')
                    ->sortable(),
                TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->money('NGN')
                    ->sortable()
                    ->toggleable(),
                IconColumn::make('open')
                    ->label('Open')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('customer_id')
                    ->relationship('customer', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('document_type')
                    ->options([
                        'SALES_INVOICE' => 'Sales Invoice',
                        'PAYMENT' => 'Payment',
                        'CASH_RECEIPT' => 'Cash Receipt',
                        'BANK_TRANSFER' => 'Bank Transfer',
                        'SALES_CREDIT_MEMO' => 'Sales Credit Memo',
                        'ADJUSTMENT' => 'Adjustment',
                    ]),
                TernaryFilter::make('open')
                    ->label('Open Entries')
                    ->boolean(),
                Filter::make('posting_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $q, $date) => $q->whereDate('posting_date', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $q, $date) => $q->whereDate('posting_date', '<=', $date));
                    }),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
