<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpeningInventories\Pages;

use App\Filament\Resources\OpeningInventories\OpeningInventoryResource;
use App\Models\OpeningInventory;
use App\Services\Inventory\OpeningInventoryService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateOpeningInventory extends CreateRecord
{
    protected static string $resource = OpeningInventoryResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $lines = $data['lines'] ?? [];
        unset($data['lines'], $data['status']);

        try {
            return app(OpeningInventoryService::class)->createDraft(
                documentNumber: (string) $data['document_number'],
                source: (string) ($data['source'] ?? 'MANUAL'),
                postingDate: $data['posting_date'] ?? now()->toDateString(),
                lines: $lines,
                businessId: isset($data['business_id']) ? (int) $data['business_id'] : null,
                createdBy: auth()->id(),
                description: $data['description'] ?? null,
            );
        } catch (ValidationException $exception) {
            $this->notifyFailure('Opening inventory was not created.', $this->validationMessage($exception));

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Opening inventory Filament create failed.', [
                'document_number' => $data['document_number'] ?? null,
                'business_id' => $data['business_id'] ?? null,
                'exception' => $exception,
            ]);

            $this->notifyFailure('Opening inventory was not created.', $exception->getMessage());

            throw ValidationException::withMessages([
                'data' => 'Opening inventory could not be created. Please review the form and try again.',
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        /** @var OpeningInventory $record */
        $record = $this->record;

        return static::getResource()::getUrl('view', ['record' => $record]);
    }

    private function notifyFailure(string $title, string $message): void
    {
        Notification::make()
            ->title($title)
            ->body($message)
            ->danger()
            ->send();
    }

    private function validationMessage(ValidationException $exception): string
    {
        return collect($exception->errors())->flatten()->first()
            ?? 'Please review the highlighted fields and try again.';
    }
}
