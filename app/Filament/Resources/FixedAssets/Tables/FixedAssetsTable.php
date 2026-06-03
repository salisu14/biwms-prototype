<?php

namespace App\Filament\Resources\FixedAssets\Tables;

use App\Enums\FAStatus;
use App\Enums\FixedAssetType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FixedAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('fa_no')
                    ->label('Asset No.')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->searchable()
                    ->limit(30),

                TextColumn::make('fa_type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('faClass.name')
                    ->label('Class')
                    ->toggleable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('acquisition_cost')
                    ->money('NGN')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('book_value')
                    ->label('Current Value')
                    ->money('NGN')
                    ->sortable(),

                IconColumn::make('blocked')
                    ->boolean()
                    ->toggleable(),

                TextColumn::make('acquisition_date')
                    ->date()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('fa_type')
                    ->options(FixedAssetType::class),
                SelectFilter::make('status')
                    ->options(FAStatus::class),
                SelectFilter::make('fa_class_id')
                    ->relationship('faClass', 'name'),
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
