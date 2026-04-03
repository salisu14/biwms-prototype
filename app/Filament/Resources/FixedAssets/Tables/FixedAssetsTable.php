<?php

namespace App\Filament\Resources\FixedAssets\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class FixedAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->sortable()->weight('bold'),
                TextColumn::make('description')->searchable(),
                TextColumn::make('asset_type')->badge(),
                TextColumn::make('status')->badge()->color(fn ($state) => $state === 'ACTIVE' ? 'success' : 'gray'),
                TextColumn::make('acquisition_cost')->money()->sortable(),
                TextColumn::make('net_book_value')->label('NBV')->money()->sortable()->color('primary'),
                TextColumn::make('location_code')->label('Loc')->toggleable(),
                TextColumn::make('acquisition_date')->date()->sortable()->toggleable(),
            ])
            ->filters([
                TrashedFilter::make(),
                SelectFilter::make('asset_type'),
                SelectFilter::make('status'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
