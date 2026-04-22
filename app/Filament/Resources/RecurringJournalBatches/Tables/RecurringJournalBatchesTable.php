<?php

namespace App\Filament\Resources\RecurringJournalBatches\Tables;

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

class RecurringJournalBatchesTable
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
                    ->label('Batch')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('template.recurring_method')
                    ->label('Method')
                    ->badge()
                    ->color(fn ($state) => match ((string) $state) {
                        'fixed' => 'info',
                        'variable' => 'warning',
                        'balance' => 'gray',
                        default => 'danger', // reversing variants
                    }),

                TextColumn::make('template.recurring_frequency')
                    ->label('Frequency')
                    ->badge()
                    ->color('gray'),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (JournalBatchStatus $state) => match ($state) {
                        JournalBatchStatus::OPEN => 'info',
                        JournalBatchStatus::PROCESSING => 'warning',
                        JournalBatchStatus::POSTED => 'success',
                        JournalBatchStatus::CANCELLED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines')
                    ->alignment('right'),

                TextColumn::make('current_processing_date')
                    ->label('Processing Date')
                    ->date()
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('template.next_posting_date')
                    ->label('Next Due')
                    ->dateTime()
                    ->since()
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('assignedUser.name')
                    ->label('Assigned To')
                    ->placeholder('—')
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

                Action::make('process')
                    ->label('Process')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->button()
                    ->hidden(fn ($record) => $record->status !== JournalBatchStatus::OPEN)
                    ->requiresConfirmation()
                    ->modalHeading('Process Recurring Batch?')
                    ->modalDescription('This will post all due lines to the General Ledger and advance the recurring schedule.')
                    ->action(function ($record) {
                        try {
                            app(JournalPostingService::class)->post($record);

                            Notification::make()
                                ->title('Recurring Batch Processed')
                                ->body("Batch {$record->name} was processed successfully.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Processing Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
