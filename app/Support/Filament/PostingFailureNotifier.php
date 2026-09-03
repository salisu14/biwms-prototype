<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Exceptions\BusinessException;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PostingFailureNotifier
{
    /**
     * Notify the operator about an expected posting failure and keep technical
     * details out of the UI for unexpected failures.
     *
     * @param  array<string, mixed>  $context
     */
    public static function notify(Throwable $exception, string $fallbackTitle, array $context = []): void
    {
        if ($exception instanceof ValidationException) {
            $message = collect($exception->errors())->flatten()->first() ?: 'Please correct the posting errors and try again.';

            Notification::make()
                ->title($fallbackTitle)
                ->body($message)
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        if ($exception instanceof BusinessException) {
            Notification::make()
                ->title($exception->title())
                ->body($exception->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        Log::error('Filament posting action failed unexpectedly.', [
            ...$context,
            'exception' => $exception,
        ]);

        Notification::make()
            ->title($fallbackTitle)
            ->body('The document was not posted. Please try again or contact your ERP administrator.')
            ->danger()
            ->persistent()
            ->send();
    }
}
