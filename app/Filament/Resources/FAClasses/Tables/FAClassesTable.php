<?php

namespace App\Filament\Resources\FAClasses\Tables;

use App\Enums\FixedAssetType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class FAClassesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Code')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('fa_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('defaultPostingGroup.code')
                    ->label('Default Posting')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('subclasses_count')
                    ->label('Subclasses')
                    ->counts('subclasses')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('fa_type')
                    ->options(FixedAssetType::class),
                SelectFilter::make('is_active')
                    ->label('Status'),
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
