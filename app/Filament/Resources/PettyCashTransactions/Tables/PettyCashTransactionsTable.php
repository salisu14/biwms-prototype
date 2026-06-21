<?php

namespace App\Filament\Resources\PettyCashTransactions\Tables;

use App\Enums\PettyCashTransactionType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PettyCashTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_number')
                    ->label('Transaction #')
                    ->searchable()
                    ->copyable()
                    ->weight('bold'),

                TextColumn::make('date')
                    ->label('Date')
                    ->date('d/m/Y')
                    ->sortable(),

                TextColumn::make('fund.name')
                    ->label('Fund')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('voucher.voucher_number')
                    ->label('Voucher #')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable()
                    ->url(fn ($record) => $record->voucher ? route('filament.admin.resources.petty-cash-vouchers.view', $record->voucher) : null),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (PettyCashTransactionType $state): string => $state->color() ?? 'gray')
                    ->searchable(),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(30)
                    ->wrap(),

                TextColumn::make('amount')
                    ->label('Amount')
                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Number::currency(abs($state), 'NGN')) // Use abs() so prefix isn't ₦-
                    ->color(fn ($state) => $state < 0 ? 'danger' : 'success')
                    ->icon(fn ($state) => $state < 0 ? 'heroicon-m-arrow-down-tray' : 'heroicon-m-arrow-up-tray')
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('running_balance')
                    ->label('Balance')
                    ->formatStateUsing(fn ($state) => \Illuminate\Support\Number::currency($state, 'NGN'))
                    ->sortable()
                    ->alignEnd(),

                TextColumn::make('created_at')
                    ->label('Created At')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('petty_cash_fund_id')
                    ->label('Fund')
                    ->relationship('fund', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('type')
                    ->label('Transaction Type')
                    ->options(PettyCashTransactionType::class),

                Filter::make('date')
                    ->schema([
                        \Filament\Forms\Components\DatePicker::make('from')->native(false),
                        \Filament\Forms\Components\DatePicker::make('until')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('date', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('date', '<=', $date));
                    }),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([

            ])
            ->defaultSort('date', 'desc'); // Show newest transactions first
    }
}
