<?php

namespace App\Filament\Resources\ItemLedgerEntries\Tables;

use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ItemLedgerEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_number')
                    ->label('#')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('entry_type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'PURCHASE' => 'success',
                        'SALE' => 'warning',
                        'POSITIVE_ADJUSTMENT' => 'info',
                        'NEGATIVE_ADJUSTMENT' => 'danger',
                        default => 'gray',
                    })
                    ->searchable(),
                TextColumn::make('document_number')
                    ->label('Doc No.')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('item.id')
                    ->label('Item')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('location.name')
                    ->label('Loc')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->sortable(),
                TextColumn::make('remaining_quantity')
                    ->label('Rem. Qty')
                    ->numeric(decimalPlaces: 2)
                    ->alignEnd()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('cost_amount_actual')
                    ->label('Cost')
                    ->money()
                    ->sortable(),
                IconColumn::make('open')
                    ->label('Open')
                    ->boolean(),
                TextColumn::make('serial_number')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('lot_number')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('entry_type')
                    ->options([
                        'PURCHASE' => 'Purchase',
                        'SALE' => 'Sale',
                        'POSITIVE_ADJUSTMENT' => 'Positive Adjustment',
                        'NEGATIVE_ADJUSTMENT' => 'Negative Adjustment',
                    ]),
                SelectFilter::make('open')
                    ->label('Entry Status')
                    ->options([
                        '1' => 'Open',
                        '0' => 'Closed',
                    ]),
                Filter::make('posting_date')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($query, $date) => $query->whereDate('posting_date', '>=', $date))
                            ->when($data['until'], fn ($query, $date) => $query->whereDate('posting_date', '<=', $date));
                    }),
            ]);
    }
}
