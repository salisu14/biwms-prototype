<?php

namespace App\Filament\Resources\ApprovalTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ApprovalTemplatesTable
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

                TextColumn::make('description')
                    ->label('Description')
                    ->searchable()
                    ->limit(40),

                TextColumn::make('document_type')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->sortable(),

                TextColumn::make('amount_limit')
                    ->label('Min. Amount')
                    ->money('NGN')
                    ->sortable()
                    ->alignment('right'),

                IconColumn::make('enabled')
                    ->label('Active')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('entries_count')
                    ->label('Steps')
                    ->counts('entries')
                    ->badge()
                    ->color('info'),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
