<?php

namespace App\Filament\Resources\SalesQuoteRevisions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SalesQuoteRevisionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                // Show the specific revision number (e.g., REV-101)
                TextColumn::make('revision_number')
                    ->label('Revision ID')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Bold),

                // Link back to the parent Quote number
                TextColumn::make('salesQuote.quote_no')
                    ->label('Quote #')
                    ->searchable()
                    ->sortable(),

                // Display version as a badge (v1, v2, etc.)
                TextColumn::make('version')
                    ->label('Ver.')
                    ->prefix('v')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                // Use your model's accessor to show which fields changed
                TextColumn::make('changes_summary')
                    ->label('Fields Modified')
                    ->placeholder('No fields logged')
                    ->badge()
                    ->separator(',')
                    ->color('gray')
                    ->limitList(3),

                // Display the revision date from your model
                TextColumn::make('revision_date')
                    ->label('Date')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),

                // Brief description/reason for change
                TextColumn::make('description')
                    ->label('Change Notes')
                    ->limit(30)
                    ->tooltip(fn ($record) => $record->description),

                // Hidden timestamps for auditing
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('revision_date', 'desc')
            ->filters([
                SelectFilter::make('sales_quote_id')
                    ->label('Filter by Quote')
                    ->relationship('salesQuote', 'quote_no')
                    ->searchable()
                    ->preload(),
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
