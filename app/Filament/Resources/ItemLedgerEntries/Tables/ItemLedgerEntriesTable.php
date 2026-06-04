<?php

namespace App\Filament\Resources\ItemLedgerEntries\Tables;

use App\Enums\ItemLedgerEntryType;
use App\Filament\Resources\Items\ItemResource;
use App\Filament\Resources\Locations\LocationResource;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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
                    ->label('Entry')
                    ->numeric()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('entry_type')
                    ->badge()
                    ->searchable()
                    ->color(fn (string $state): string => match ($state) {
                        'PURCHASE', 'POSITIVE_ADJUSTMENT', 'TRANSFER', 'OUTPUT', 'ASSEMBLY_OUTPUT' => 'success',
                        'SALE', 'NEGATIVE_ADJUSTMENT', 'CONSUMPTION', 'ASSEMBLY_CONSUMPTION' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('document_number')
                    ->label('Doc No.')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('item.item_code')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(fn ($state, $record): string => $record->item
                        ? "{$record->item->item_code} - {$record->item->description}"
                        : '—')
                    ->url(fn ($record): ?string => $record->item
                        ? ItemResource::getUrl('view', ['record' => $record->item])
                        : null)
                    ->description(fn ($record): string => $record->item?->description ?? ''),
                TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable()
                    ->formatStateUsing(fn ($state, $record): string => $record->location
                        ? "{$record->location->code} - {$record->location->name}"
                        : '—')
                    ->url(fn ($record): ?string => $record->location
                        ? LocationResource::getUrl('view', ['record' => $record->location])
                        : null),
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
                    ->options(ItemLedgerEntryType::class),
                SelectFilter::make('item_id')
                    ->relationship('item', 'description')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('location_id')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),
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
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()->label('Delete Selected'),
                ]),
            ]);
    }
}
