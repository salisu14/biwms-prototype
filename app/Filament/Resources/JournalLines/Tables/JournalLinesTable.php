<?php

namespace App\Filament\Resources\JournalLines\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class JournalLinesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('line_no')->sortable(),
                TextColumn::make('posting_date')->date()->sortable(),
                TextColumn::make('account_no')->searchable(),
                TextColumn::make('description')->limit(30),
                TextColumn::make('amount')->money('USD')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'success' => 'Posted',
                        'warning' => 'Open',
                        'danger' => 'Reversed',
                    ]),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'Open' => 'Open',
                    'Posted' => 'Posted',
                    'Reversed' => 'Reversed',
                ]),
            ])
            ->defaultSort('line_no', 'asc')
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
