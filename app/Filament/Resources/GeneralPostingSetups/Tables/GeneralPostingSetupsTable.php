<?php

namespace App\Filament\Resources\GeneralPostingSetups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GeneralPostingSetupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('generalBusinessPostingGroup.code')
                    ->label('Bus. Group')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('generalProductPostingGroup.code')
                    ->label('Prod. Group')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('salesAccount.name')
                    ->label('Sales Account')
                    ->toggleable(),
                TextColumn::make('cogsAccount.name')
                    ->label('COGS Account')
                    ->toggleable(),
                TextColumn::make('inventoryAccount.name')
                    ->label('Inventory Account')
                    ->toggleable(),
                IconColumn::make('blocked')
                    ->boolean()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('blocked'),
                SelectFilter::make('general_business_posting_group_id')
                    ->relationship('generalBusinessPostingGroup', 'code')
                    ->label('Business Group'),
                SelectFilter::make('general_product_posting_group_id')
                    ->relationship('generalProductPostingGroup', 'code')
                    ->label('Product Group'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
