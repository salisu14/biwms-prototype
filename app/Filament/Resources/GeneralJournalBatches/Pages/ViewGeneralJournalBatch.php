<?php

namespace App\Filament\Resources\GeneralJournalBatches\Pages;

use App\Enums\JournalBatchStatus;
use App\Filament\Resources\GeneralJournalBatches\GeneralJournalBatchResource;
use App\Services\Posting\JournalPostingService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewGeneralJournalBatch extends ViewRecord
{
    protected static string $resource = GeneralJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->hidden(fn () => $this->record->status === JournalBatchStatus::POSTED),

            Action::make('release')
                ->label('Release')
                ->icon('heroicon-o-arrow-up-circle')
                ->color('warning')
                ->hidden(fn () => $this->record->status !== JournalBatchStatus::OPEN)
                ->requiresConfirmation()
                ->modalHeading('Release Batch?')
                ->modalDescription('Validates the batch is balanced and marks it as ready to post.')
                ->action(function () {
                    try {
                        $this->record->release();
                        $this->refreshFormData(['status']);

                        Notification::make()
                            ->title('Batch Released')
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
                ->label('Post Journal')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->hidden(fn () => $this->record->status !== JournalBatchStatus::RELEASED)
                ->requiresConfirmation()
                ->modalHeading('Post Journal Batch?')
                ->modalDescription('All lines will be permanently posted to the General Ledger. This cannot be undone.')
                ->action(function () {
                    try {
                        app(JournalPostingService::class)->post($this->record);
                        $this->refreshFormData(['status']);

                        Notification::make()
                            ->title('Batch Posted to G/L')
                            ->body("Batch {$this->record->name} has been posted successfully.")
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

            DeleteAction::make()
                ->hidden(fn () => $this->record->status === JournalBatchStatus::POSTED),
        ];
    }
}
