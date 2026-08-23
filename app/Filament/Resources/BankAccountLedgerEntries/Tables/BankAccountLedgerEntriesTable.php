<?php

declare(strict_types=1);

namespace App\Filament\Resources\BankAccountLedgerEntries\Tables;

use App\Enums\BankAccountLedgerEntryStatus;
use App\Enums\BankAccountLedgerEntryType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Number;

class BankAccountLedgerEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_number')
                    ->label('Entry #')
                    ->searchable()
                    ->copyable()
                    ->weight('bold')
                    ->sortable(),
                TextColumn::make('bankAccount.bank_name')
                    ->label('Bank Account')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('posting_date')
                    ->label('Posting Date')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('document_no')
                    ->label('Document #')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('description')
                    ->searchable()
                    ->limit(30)
                    ->wrap(),
                TextColumn::make('entry_type')
                    ->badge()
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn (mixed $state): string => self::formatAbsoluteMoney($state))
                    ->color(fn (mixed $state): string => self::isNegativeAmount($state) ? 'danger' : 'success')
                    ->icon(fn (mixed $state): string => self::isNegativeAmount($state) ? 'heroicon-m-arrow-down-tray' : 'heroicon-m-arrow-up-tray')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('balance')
                    ->label('Balance')
                    ->formatStateUsing(fn (mixed $state): string => self::formatMoney($state))
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (BankAccountLedgerEntryStatus $state) => $state->color())
                    ->searchable(),
                IconColumn::make('open')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                // Hidden by default to keep table clean
                TextColumn::make('check_no')->label('Check #')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('glEntry.entry_number')->label('G/L Entry')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('bank_account_id')
                    ->label('Bank Account')
                    ->relationship('bankAccount', 'bank_name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('entry_type')
                    ->options(BankAccountLedgerEntryType::class),
                SelectFilter::make('status')
                    ->options(BankAccountLedgerEntryStatus::class),
                Filter::make('posting_date')
                    ->schema([
                        DatePicker::make('from')->native(false),
                        DatePicker::make('until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('posting_date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('posting_date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
                // NOTE: EditAction is intentionally removed. Ledger entries should never be edited directly after posting.
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(), // Intentionally removed. Ledger entries shouldn't be deleted.
                ]),
            ])
            ->defaultSort('posting_date', 'desc');
    }

    private static function formatAbsoluteMoney(mixed $state): string
    {
        return Number::currency(abs(self::numericAmount($state)), 'NGN');
    }

    private static function formatMoney(mixed $state): string
    {
        return Number::currency(self::numericAmount($state), 'NGN');
    }

    private static function isNegativeAmount(mixed $state): bool
    {
        return self::numericAmount($state) < 0;
    }

    private static function numericAmount(mixed $state): float
    {
        return (float) ($state ?? 0);
    }
}
