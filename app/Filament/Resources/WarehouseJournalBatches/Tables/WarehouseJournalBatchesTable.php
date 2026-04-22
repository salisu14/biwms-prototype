<?php

namespace App\Filament\Resources\WarehouseJournalBatches\Tables;

use App\Enums\JournalBatchStatus;
use App\Services\Posting\WarehouseJournalPostingRoutine;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class WarehouseJournalBatchesTable
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

                TextColumn::make('journal_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'movement' => 'info',
                        'pick' => 'warning',
                        'put_away' => 'success',
                        'physical_inventory' => 'gray',
                        'adjustment' => 'danger',
                        null => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pick' => 'Pick',
                        'put_away' => 'Put-Away',
                        'movement' => 'Movement',
                        'physical_inventory' => 'Phys. Inventory',
                        'adjustment' => 'Adjustment',
                        default => 'From Template',
                    }),

                TextColumn::make('location.name')
                    ->label('Location')
                    ->searchable(),

                TextColumn::make('zone.code')
                    ->label('Zone')
                    ->placeholder('All')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (JournalBatchStatus $state) => match ($state) {
                        JournalBatchStatus::OPEN => 'info',
                        JournalBatchStatus::RELEASED => 'warning',
                        JournalBatchStatus::POSTED => 'success',
                        JournalBatchStatus::CANCELLED => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('lines_count')
                    ->counts('lines')
                    ->label('Lines')
                    ->alignment('right'),

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
                SelectFilter::make('location')
                    ->relationship('location', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('journal_type')
                    ->options([
                        'pick' => 'Pick',
                        'put_away' => 'Put-Away',
                        'movement' => 'Movement',
                        'physical_inventory' => 'Physical Inventory',
                        'adjustment' => 'Adjustment',
                    ])
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->hidden(fn ($record) => $record->status === JournalBatchStatus::POSTED),

                Action::make('register')
                    ->label('Register')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->button()
                    ->hidden(fn ($record) => $record->status !== JournalBatchStatus::OPEN)
                    ->requiresConfirmation()
                    ->modalHeading('Register Warehouse Journal?')
                    ->modalDescription('All lines will be registered as warehouse entries. This creates bin-level movements but does not post to the G/L.')
                    ->action(function ($record) {
                        try {
                            app(WarehouseJournalPostingRoutine::class)->post($record);

                            Notification::make()
                                ->title('Journal Registered')
                                ->body("Batch {$record->name} registered successfully.")
                                ->success()
                                ->send();
                        } catch (\Exception $e) {
                            Notification::make()
                                ->title('Registration Failed')
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
