<?php

declare(strict_types=1);

namespace App\Filament\Resources\SalesCreditMemos\Pages;

use App\Contracts\ApprovableStatus;
use App\Filament\Resources\SalesCreditMemos\Concerns\InteractsWithSalesCreditMemoApplications;
use App\Filament\Resources\SalesCreditMemos\SalesCreditMemoResource;
use App\Models\PostedSalesCreditMemo;
use App\Services\Approval\ApprovalService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesCreditMemo extends ViewRecord
{
    use InteractsWithSalesCreditMemoApplications;

    protected static string $resource = SalesCreditMemoResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->authorize(fn ($record): bool => auth()->user()?->can('update', $record) === true)
                ->visible(fn ($record): bool => ! $record->isPosted()),

            $this->applyCreditMemoAction(),

            Action::make('submit_for_approval')
                ->label('Submit for Approval')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->authorize(fn ($record): bool => auth()->user()?->can('submit', $record) === true)
                ->visible(fn ($record): bool => $record->status instanceof ApprovableStatus && $record->status->canSubmitForApproval())
                ->action(function ($record) {
                    app(ApprovalService::class)->submitForApproval($record);

                    Notification::make()
                        ->title('Submitted for approval')
                        ->success()
                        ->send();
                }),

            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn ($record): bool => ! $record->isPosted() && $record->approvalEntries()->where('status', 'created')
                    ->where(function ($q) {
                        $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id());
                    })
                    ->exists())
                ->requiresConfirmation()
                ->action(function ($record) {
                    $entry = $record->approvalEntries()->where('status', 'created')
                        ->where(function ($q) {
                            $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id());
                        })
                        ->orderBy('sequence_no')
                        ->first();

                    if (! $entry) {
                        Notification::make()->title('No pending approval')->danger()->send();

                        return;
                    }

                    app(ApprovalService::class)->approve($entry);

                    Notification::make()
                        ->title('Approved')
                        ->success()
                        ->send();
                }),

            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn ($record): bool => ! $record->isPosted() && $record->approvalEntries()->where('status', 'created')
                    ->where(function ($q) {
                        $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id());
                    })
                    ->exists())
                ->form([
                    Textarea::make('reason')
                        ->label('Reason')
                        ->required(),
                ])
                ->action(function ($record, array $data) {
                    $entry = $record->approvalEntries()->where('status', 'created')
                        ->where(function ($q) {
                            $q->where('approver_id', auth()->id())->orWhere('delegated_to', auth()->id());
                        })
                        ->orderBy('sequence_no')
                        ->first();

                    if (! $entry) {
                        Notification::make()->title('No pending approval')->danger()->send();

                        return;
                    }

                    app(ApprovalService::class)->reject($entry, $data['reason']);

                    Notification::make()
                        ->title('Rejected')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function applicationPostedSalesCreditMemo(): ?PostedSalesCreditMemo
    {
        if (! $this->record->isPosted()) {
            return null;
        }

        return PostedSalesCreditMemo::query()
            ->where('document_number', $this->record->memo_number)
            ->where('customer_id', $this->record->customer_id)
            ->latest('id')
            ->first();
    }

    protected function refreshAfterCreditMemoApplication(?PostedSalesCreditMemo $postedCreditMemo): void
    {
        $this->record = $this->record->fresh(['items.item', 'customer', 'invoice', 'postedInvoice']);
    }
}
