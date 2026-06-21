<?php

namespace App\Filament\Resources\GeneralJournalBatches\Tables;

use App\Enums\JournalBatchStatus;
use App\Services\Posting\JournalPostingService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GeneralJournalBatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('template.name')
                    ->label('Template')
                    ->sortable()
                    ->searchable(),

                TextColumn::make('name')
                    ->label('Batch Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('description')
                    ->limit(35)
                    ->placeholder('—'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (JournalBatchStatus $state) => match ($state) {
                        JournalBatchStatus::OPEN => 'info',
                        JournalBatchStatus::RELEASED => 'warning',
                        JournalBatchStatus::POSTED => 'success',
                        JournalBatchStatus::CANCELLED => 'danger',
                        JournalBatchStatus::PROCESSING => 'gray',
                    }),

                TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines')
                    ->alignment('right'),

                TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('balancingAccount.name')
                    ->label('Bal. Account')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('posting_date_restriction_from')
                    ->label('Date From')
                    ->date()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('posting_date_restriction_to')
                    ->label('Date To')
                    ->date()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Last Modified')
                    ->since()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(JournalBatchStatus::class)
                    ->native(false),

                SelectFilter::make('template')
                    ->relationship('template', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->hidden(fn ($record) => $record->status === JournalBatchStatus::POSTED),

                Action::make('release')
                    ->label('Release')
                    ->icon('heroicon-o-arrow-up-circle')
                    ->color('warning')
                    ->button()
                    ->hidden(fn ($record) => $record->status !== JournalBatchStatus::OPEN)
                    ->requiresConfirmation()
                    ->modalHeading('Release Batch?')
                    ->modalDescription('This will validate the batch is balanced and mark it as ready to post.')
                    ->action(function ($record) {
                        try {
                            $record->release();

                            Notification::make()
                                ->title('Batch Released')
                                ->body("Batch {$record->name} is ready to post.")
                                ->success()
                                ->send();
                        } catch (\RuntimeException $e) {
                            Notification::make()
                                ->title('Release Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                Action::make('post')
                    ->label('Post')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->hidden(fn ($record) => $record->status !== JournalBatchStatus::RELEASED)
                    ->requiresConfirmation()
                    ->modalHeading('Post Journal Batch?')
                    ->modalDescription('All lines will be posted to the General Ledger. This cannot be undone.')
                    ->action(function ($record) {
                        try {
                            app(JournalPostingService::class)->post($record);

                            Notification::make()
                                ->title('Batch Posted')
                                ->body("Batch {$record->name} has been posted to the G/L.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Posting Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->hidden(fn () => false),
                ]),
            ]);
    }
}
