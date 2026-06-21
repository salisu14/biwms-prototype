<?php

namespace App\Filament\Traits;

use Filament\Notifications\Notification;

trait PreventsEditingPostedRecords
{
    public function mount(int|string $record): void
    {
        parent::mount($record);

        if ($this->isPosted() && ! auth()->user()?->hasRole('SUPER_ADMIN')) {
            Notification::make()
                ->warning()
                ->title('Read-Only Record')
                ->body('This record has been posted and cannot be modified.')
                ->send();

            // Use correct redirect method
            $this->redirect($this->getResource()::getUrl('view', ['record' => $record]));
        }
    }

    protected function isPosted(): bool
    {
        $record = $this->record;

        // Check various "posted" conditions
        return method_exists($record, 'isPosted') && $record->isPosted()
            || ! is_null($record->posted_at ?? null)
            || ($record->status ?? null) === 'posted'
            || ($record->completely_shipped ?? false);
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->visible(fn (): bool => ! $this->isPosted() || (auth()->user()?->hasRole('SUPER_ADMIN') ?? false)),
            $this->getCancelFormAction(),
        ];
    }
}
