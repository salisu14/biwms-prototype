<?php

namespace App\Filament\Resources\FAJournalBatches\Pages;

use App\Filament\Resources\FAJournalBatches\FAJournalBatchResource;
use App\Services\FixedAsset\DepreciationBatchService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFAJournalBatch extends ViewRecord
{
    protected static string $resource = FAJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),

            Action::make('post')
                ->label('Post Batch')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->hidden(fn () => $this->record->status !== 'released')
                ->requiresConfirmation()
                ->modalHeading('Post FA Journal Batch?')
                ->modalDescription('All lines will be permanently posted to the Fixed Assets ledger. This cannot be undone.')
                ->action(function () {
                    try {
                        app(DepreciationBatchService::class)->postBatch($this->record);
                        $this->refreshFormData(['status']);

                        \Filament\Notifications\Notification::make()
                            ->title('Batch Posted')
                            ->body("Batch {$this->record->name} has been posted successfully.")
                            ->success()
                            ->send();
                    } catch (\Exception $e) {
                        \Filament\Notifications\Notification::make()
                            ->title('Posting Failed')
                            ->body($e->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
