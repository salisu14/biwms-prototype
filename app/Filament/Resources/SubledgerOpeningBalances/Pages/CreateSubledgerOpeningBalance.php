<?php

declare(strict_types=1);

namespace App\Filament\Resources\SubledgerOpeningBalances\Pages;

use App\Exceptions\BusinessException;
use App\Filament\Resources\SubledgerOpeningBalances\SubledgerOpeningBalanceResource;
use App\Services\Finance\SubledgerOpeningBalanceService;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CreateSubledgerOpeningBalance extends CreateRecord
{
    protected static string $resource = SubledgerOpeningBalanceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return app(SubledgerOpeningBalanceService::class)->createDraft($data, auth()->id());
        } catch (ValidationException $exception) {
            $this->notifyFailure('Opening balance was not created', $this->validationMessage($exception));

            throw $exception;
        } catch (BusinessException $exception) {
            $this->notifyFailure($exception->title(), $exception->getMessage());

            throw ValidationException::withMessages([
                $exception->field() => $exception->getMessage(),
            ]);
        } catch (Throwable $exception) {
            Log::error('Subledger opening balance draft creation failed.', [
                'party_type' => $data['party_type'] ?? null,
                'party_id' => $data['party_id'] ?? null,
                'business_id' => $data['business_id'] ?? null,
                'exception' => $exception,
            ]);
            $this->notifyFailure('Opening balance was not created', 'The draft could not be saved. Please review the form or contact your ERP administrator.');

            throw ValidationException::withMessages([
                'data' => 'The opening balance draft could not be saved.',
            ]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return self::getResource()::getUrl('view', ['record' => $this->record]);
    }

    private function notifyFailure(string $title, string $message): void
    {
        Notification::make()
            ->title($title)
            ->body($message)
            ->danger()
            ->persistent()
            ->send();
    }

    private function validationMessage(ValidationException $exception): string
    {
        return collect($exception->errors())->flatten()->first()
            ?? 'Please review the highlighted fields and try again.';
    }
}
