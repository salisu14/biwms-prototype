<?php

namespace App\Filament\Resources\VatBusinessPostingGroups\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class VatBusinessPostingGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
