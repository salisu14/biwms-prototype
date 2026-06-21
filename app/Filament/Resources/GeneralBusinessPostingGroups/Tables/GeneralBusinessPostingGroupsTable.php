<?php

namespace App\Filament\Resources\GeneralBusinessPostingGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class GeneralBusinessPostingGroupsTable
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

                TextColumn::make('defaultVatBusinessPostingGroup.code')
                    ->label('Default VAT Group')
                    ->badge()
                    ->color('info')
                    ->placeholder('None')
                    ->sortable(),

                TextColumn::make('customers_count')
                    ->label('Customers')
                    ->counts('customers')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('vendors_count')
                    ->label('Vendors')
                    ->counts('vendors')
                    ->badge()
                    ->color('gray'),

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
                SelectFilter::make('default_vat_business_posting_group_id')
                    ->label('VAT Group')
                    ->relationship('defaultVatBusinessPostingGroup', 'code'),

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
