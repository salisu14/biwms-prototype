<?php

namespace App\Filament\Resources\GeneralProductPostingGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GeneralProductPostingGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Group Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(50),

                TextColumn::make('defaultVatProductPostingGroup.code')
                    ->label('Default VAT Group')
                    ->badge()
                    ->color('info')
                    ->placeholder('None')
                    ->sortable(),

                TextColumn::make('items_count')
                    ->label('Linked Items')
                    ->counts('items')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('auto_create_vat_prod_posting_group')
                    ->label('Auto-VAT')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),

                IconColumn::make('blocked')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-x-circle')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('default_vat_product_posting_group_id')
                    ->label('VAT Group')
                    ->relationship('defaultVatProductPostingGroup', 'code'),

                TernaryFilter::make('blocked')
                    ->label('Usage Status')
                    ->boolean()
                    ->trueLabel('Blocked Only')
                    ->falseLabel('Active Only')
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('code', 'asc');
    }
}
