<?php

namespace App\Filament\Resources\VendorPostingGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VendorPostingGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('description')
                    ->label('Posting Group')
                    ->searchable(),
                TextColumn::make('payablesAccount.name')
                    ->label('Payables Account')
                    ->description(fn ($record) => $record->payablesAccount?->no ?? '')
                    ->searchable(),
                IconColumn::make('blocked')
                    ->label('Blocked')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
