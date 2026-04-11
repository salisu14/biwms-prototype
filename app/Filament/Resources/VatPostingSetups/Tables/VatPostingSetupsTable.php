<?php

namespace App\Filament\Resources\VatPostingSetups\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VatPostingSetupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vatBusinessPostingGroup.code')
                    ->label('Bus. Posting Group')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vatProductPostingGroup.code')
                    ->label('Prod. Posting Group')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('vat_percentage')
                    ->label('VAT %')
                    ->numeric(2)
                    ->sortable()
                    ->suffix('%'),
                TextColumn::make('salesVatAccount.account_number')
                    ->label('Sales Acc.')
                    ->searchable(),
                TextColumn::make('purchaseVatAccount.account_number')
                    ->label('Purch. Acc.')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
