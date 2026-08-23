<?php

declare(strict_types=1);

namespace App\Filament\Sales\Resources\SalesOrders\Pages;

use App\Filament\Sales\Resources\SalesOrders\SalesOrderResource;
use App\Models\SalesOrderLine;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreateSalesOrder extends CreateRecord
{
    protected static string $resource = SalesOrderResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    public function create(bool $another = false): void
    {
        try {
            parent::create($another);
        } catch (ValidationException $exception) {
            $this->notifyFailure('Sales order was not created.', $this->validationMessage($exception));

            throw $exception;
        } catch (Throwable $exception) {
            Log::error('Sales order Filament create failed.', [
                'customer_id' => $this->form->getRawState()['customer_id'] ?? null,
                'status' => $this->form->getRawState()['status'] ?? null,
                'exception' => $exception,
            ]);

            $this->notifyFailure('Sales order was not created.', 'Sales order could not be saved. Please review the form and try again.');

            throw ValidationException::withMessages([
                'data' => 'Sales order could not be saved. Please review the form and try again.',
            ]);
        }
    }

    protected function afterCreate(): void
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

        $this->record->recalculateTotals();
        $this->record->save();
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
