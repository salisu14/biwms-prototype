<?php

namespace App\Filament\Resources\InventoryAdjustmentJournals\Tables;

use App\Jobs\PostInventoryAdjustment;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class InventoryAdjustmentJournalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('journal_batch_name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('description')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('posting_date')
                    ->date()
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->colors([
                        'warning' => 'Open',
                        'primary' => 'Released',
                        'success' => 'Posted',
                    ]),

                TextColumn::make('location_code')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines'),

                TextColumn::make('total_amount')
                    ->label('Total Amount')
                    ->getStateUsing(fn ($record) => $record->lines->sum('amount'))
                    ->money('USD')
                    ->toggleable(),

                TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->toggleable(),

                TextColumn::make('posted_at')
                    ->dateTime()
                    ->toggleable()
                    ->toggledHiddenByDefault(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Open' => 'Open',
                        'Released' => 'Released',
                        'Posted' => 'Posted',
                    ]),
                SelectFilter::make('location_code')
                    ->relationship('location', 'code'),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn ($record) => $record->status !== 'Posted'),

                Action::make('release')
                    ->label('Release')
                    ->icon('heroicon-o-lock-open')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'Open')
                    ->action(fn ($record) => $record->update(['status' => 'Released'])),

                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-lock-open')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'Released')
                    ->action(fn ($record) => $record->update(['status' => 'Open'])),

                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn ($record) => $record->status === 'Released' && $record->canPost())
                    ->action(function ($record) {
                        // Dispatch to job/queue for posting logic
                        // This creates Item Ledger Entries, Value Entries, etc.
                        PostInventoryAdjustment::dispatch($record);
                    }),

                ViewAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn () => false), // Prevent bulk delete - BC style,
                ]),
            ]);
    }
}
