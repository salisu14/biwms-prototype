<?php

namespace App\Filament\Resources\FAJournalTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FAJournalTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Template')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('template_type')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('defaultDepreciationBook.code')
                    ->label('Default Book')
                    ->sortable(),

                TextColumn::make('batches_count')
                    ->label('Batches')
                    ->counts('batches')
                    ->badge()
                    ->color('gray'),

                IconColumn::make('is_active')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('template_type')
                    ->options([
                        'Assets' => 'Fixed Assets',
                        'Insurance' => 'Insurance',
                        'Maintenance' => 'Maintenance',
                    ]),

                TernaryFilter::make('is_active')
                    ->label('Active Status'),

                SelectFilter::make('default_depreciation_book_id')
                    ->relationship('defaultDepreciationBook', 'code'),
            ])
            ->defaultSort('name', 'asc')
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
