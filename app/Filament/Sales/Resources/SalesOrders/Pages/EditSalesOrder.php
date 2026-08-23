<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\SalesOrders\Pages;

use App\Filament\Sales\Resources\SalesOrders\SalesOrderResource;
use App\Models\SalesOrderLine;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditSalesOrder extends EditRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        try {
            parent::save($shouldRedirect, $shouldSendSavedNotification);
        } catch (ValidationException $exception) {
            $this->notifyFailure('Sales order was not saved.', $this->validationMessage($exception));

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Sales order Filament edit failed.', [
                'sales_order_id' => $this->record?->getKey(),
                'order_number' => $this->record?->getAttribute('order_number'),
                'exception' => $exception,
            ]);

            $this->notifyFailure('Sales order was not saved.', 'Sales order could not be saved. Please review the form and try again.');

            throw ValidationException::withMessages([
                'data' => 'Sales order could not be saved. Please review the form and try again.',
            ]);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $this->finalizeSalesOrderPersistence();
    }

    protected function finalizeSalesOrderPersistence(): void
    {
        $this->record->refresh()->load('lines');

        $hasValidLine = $this->record->lines
            ->contains(fn (SalesOrderLine $line): bool => (float) $line->quantity > 0);

        if (! $hasValidLine) {
            throw ValidationException::withMessages([
                'lines' => 'Sales order requires at least one item line with a quantity greater than zero.',
            ]);
        }

        $this->record->saveRecalculatedTotalsFromPersistedLines();
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
