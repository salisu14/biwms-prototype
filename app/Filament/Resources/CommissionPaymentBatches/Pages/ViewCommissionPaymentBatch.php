<?php

declare(strict_types=1);

namespace App\Filament\Resources\CommissionPaymentBatches\Pages;

use App\Enums\CommissionPaymentBatchStatus;
use App\Filament\Resources\CommissionPaymentBatches\CommissionPaymentBatchResource;
use App\Services\Sales\ReferralCommissions\CommissionPaymentReversalService;
use App\Services\Sales\ReferralCommissions\CommissionPaymentService;
use App\Support\Filament\SensitiveActionPasswordConfirmation;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewCommissionPaymentBatch extends ViewRecord
{
    protected static string $resource = CommissionPaymentBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prepare')
                ->visible(fn (): bool => $this->record->status === CommissionPaymentBatchStatus::Draft)
                ->requiresConfirmation()
                ->action(fn (): mixed => $this->record = app(CommissionPaymentService::class)->prepare($this->record, auth()->user())),
            Action::make('submit')
                ->visible(fn (): bool => $this->record->status === CommissionPaymentBatchStatus::Prepared)
                ->requiresConfirmation()
                ->action(fn (): mixed => $this->record = app(CommissionPaymentService::class)->submit($this->record, auth()->user())),
            SensitiveActionPasswordConfirmation::protect(Action::make('approve')
                ->visible(fn (): bool => $this->record->status === CommissionPaymentBatchStatus::Submitted)
                ->action(fn (): mixed => $this->record = app(CommissionPaymentService::class)->approve($this->record, auth()->user()))),
            Action::make('post')
                ->visible(fn (): bool => $this->record->status === CommissionPaymentBatchStatus::Approved)
                ->requiresConfirmation()
                ->action(fn (): mixed => $this->record = app(CommissionPaymentService::class)->post($this->record, auth()->user())),
            Action::make('reverse')
                ->color('danger')
                ->visible(fn (): bool => $this->record->status === CommissionPaymentBatchStatus::Posted)
                ->requiresConfirmation()
                ->action(fn (): mixed => $this->record = app(CommissionPaymentReversalService::class)->reverseBatch($this->record, auth()->user())),
        ];
    }
}
