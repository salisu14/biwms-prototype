<?php

namespace App\Filament\Resources\VendorInvoices\Tables;

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
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Support\Number;

class VendorInvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('document_number')
                    ->label('Invoice No.')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),
                TextColumn::make('vendor.vendor_name')
                    ->label('Vendor')
                    ->searchable()
                    ->sortable()
                    ->limit(25)
                    ->description(fn ($record) => $record->vendor?->vendor_code),
                TextColumn::make('vendor_invoice_no')
                    ->label('Vendor Inv. No.')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'OPEN' => 'gray',
                        'APPROVED' => 'info',
                        'POSTED' => 'success',
                        'PAID' => 'primary',
                        default => 'warning',
                    }),
                TextColumn::make('payment_status')
                    ->label('Payment')
                    ->state(fn ($record) => $record->getPaymentStatus())
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PAID' => 'success',
                        'PARTIAL' => 'warning',
                        'UNPAID' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('amount_including_tax')
                    ->label('Total Amount')
                    ->formatStateUsing(fn ($state, $record) => Number::currency((float) $state, $record->currency_code ?: config('app.default_currency', 'USD')))
                    ->sortable(),
                TextColumn::make('remaining_amount')
                    ->label('Remaining')
                    ->formatStateUsing(fn ($state, $record) => Number::currency((float) $state, $record->currency_code ?: config('app.default_currency', 'USD')))
                    ->sortable()
                    ->color('danger'),
                TextColumn::make('posting_date')
                    ->label('Posting Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date('d/m/Y')
                    ->sortable()
                    ->color(fn ($record) => $record->due_date < now() && $record->remaining_amount > 0 ? 'danger' : 'gray'),
                TextColumn::make('days_overdue')
                    ->label('Overdue')
                    ->state(fn ($record) => $record->getDaysOverdue())
                    ->numeric()
                    ->suffix(' days')
                    ->color('danger')
                    ->toggleable(),
                IconColumn::make('posted')
                    ->label('Posted')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('capExProject.description')
                    ->label('CapEx')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('vendor_id')
                    ->label('Vendor')
                    ->relationship('vendor', 'vendor_name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('status')
                    ->options(['OPEN' => 'Open', 'APPROVED' => 'Approved', 'POSTED' => 'Posted', 'PAID' => 'Paid']),
                TernaryFilter::make('posted')
                    ->label('Posted Status')
                    ->trueLabel('Posted Only')
                    ->falseLabel('Unposted Only'),
                TernaryFilter::make('overdue')
                    ->label('Overdue')
                    ->trueLabel('Overdue Only')
                    ->queries(
                        true: fn ($query) => $query->overdue(),
                        false: fn ($query) => $query->where('due_date', '>=', now())->orWhere('remaining_amount', '<=', 0),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()->hidden(fn ($record) => $record->posted),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('posting_date', 'desc');
    }
}
