<?php

namespace App\Filament\Resources\ValueEntries\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ValueEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('entry_no')->sortable(),
                TextColumn::make('posting_date')->date()->sortable(),
                TextColumn::make('item_no')->label('Item No')->searchable(),
                TextColumn::make('item.description')
                    ->label('Description')
                    ->toggleable()
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('item', function (Builder $itemQuery) use ($search): void {
                            $itemQuery->where('description', 'ilike', "%{$search}%");
                        });
                    }),
                TextColumn::make('item_ledger_entry_type')->badge()->sortable(),
//                TextColumn::make('cost_amount_expected')->money('NGN')->sortable(),
                TextColumn::make('cost_amount_actual')->money('NGN')->sortable(),
                IconColumn::make('gl_posted')->boolean(),
                IconColumn::make('cost_adjusted')->boolean()->label('Adjusted'),
            ])
            ->defaultSort('entry_no', 'desc')
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
