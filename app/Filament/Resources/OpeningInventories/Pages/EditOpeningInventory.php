<?php

declare(strict_types=1);

namespace App\Filament\Resources\OpeningInventories\Pages;

use App\Filament\Resources\OpeningInventories\OpeningInventoryResource;
use App\Models\OpeningInventory;
use App\Services\Inventory\OpeningInventoryService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditOpeningInventory extends EditRecord
{
    protected static string $resource = OpeningInventoryResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var OpeningInventory $record */
        $record = $this->record->load('lines');
        $data['lines'] = $record->lines
            ->sortBy('line_number')
            ->map(fn ($line): array => [
                'id' => $line->id,
                'line_number' => $line->line_number,
                'item_id' => $line->item_id,
                'location_id' => $line->location_id,
                'unit_of_measure_id' => $line->unit_of_measure_id,
                'quantity' => $line->quantity,
                'quantity_base' => $line->quantity_base,
                'unit_cost' => $line->unit_cost,
                'amount' => $line->amount,
                'lot_number' => $line->lot_number,
                'serial_number' => $line->serial_number,
            ])
            ->values()
            ->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $lines = $data['lines'] ?? [];
        unset($data['lines'], $data['status']);

        try {
            return app(OpeningInventoryService::class)->updateDraft($record, $data, $lines);
        } catch (ValidationException $exception) {
            $this->notifyFailure('Opening inventory was not saved.', $this->validationMessage($exception));

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Opening inventory Filament edit failed.', [
                'opening_inventory_id' => $record->getKey(),
                'document_number' => $record->getAttribute('document_number'),
                'business_id' => $data['business_id'] ?? $record->getAttribute('business_id'),
                'exception' => $exception,
            ]);

            $this->notifyFailure('Opening inventory was not saved.', $exception->getMessage());

            throw ValidationException::withMessages([
                'data' => 'Opening inventory could not be saved. Please review the form and try again.',
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            SensitiveActionPasswordConfirmation::protect(
                Action::make('post')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (OpeningInventory $record): bool => auth()->user()?->can('post', $record) === true)
                    ->action(function (OpeningInventory $record): void {
                        try {
                            app(OpeningInventoryService::class)->post($record, auth()->id());
                            Notification::make()->title('Opening inventory posted')->success()->send();
                        } catch (Throwable $exception) {
                            Log::error('Opening inventory edit post failed.', [
                                'opening_inventory_id' => $record->getKey(),
                                'document_number' => $record->document_number,
                                'exception' => $exception,
                            ]);

                            $this->notifyFailure('Opening inventory was not posted.', $exception->getMessage());
                        }
                    })
            ),
            SensitiveActionPasswordConfirmation::protect(
                Action::make('cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (OpeningInventory $record): bool => auth()->user()?->can('cancel', $record) === true)
                    ->action(function (OpeningInventory $record): void {
                        try {
                            app(OpeningInventoryService::class)->cancelDraft($record, auth()->id());
                            Notification::make()->title('Opening inventory cancelled')->success()->send();
                        } catch (Throwable $exception) {
                            Log::error('Opening inventory edit cancel failed.', [
                                'opening_inventory_id' => $record->getKey(),
                                'document_number' => $record->document_number,
                                'exception' => $exception,
                            ]);

                            $this->notifyFailure('Opening inventory was not cancelled.', $exception->getMessage());
                        }
                    })
            ),
            DeleteAction::make()
                ->visible(fn (OpeningInventory $record): bool => auth()->user()?->can('delete', $record) === true),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->record]);
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
