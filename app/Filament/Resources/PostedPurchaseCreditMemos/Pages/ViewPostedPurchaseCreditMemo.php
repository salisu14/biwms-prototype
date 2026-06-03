<?php

namespace App\Filament\Resources\PostedPurchaseCreditMemos\Pages;

use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Resources\PostedPurchaseCreditMemos\PostedPurchaseCreditMemoResource;
use App\Models\PaymentApplication;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\VendorLedgerEntry;
use Filament\Actions\Action;
use Filament\Resources\Pages\Page;
use Illuminate\Support\Collection;

class ViewPostedPurchaseCreditMemo extends Page
{
    protected static string $resource = PostedPurchaseCreditMemoResource::class;

    protected string $view = 'filament.resources.posted-purchase-credit-memos.pages.view-posted-purchase-credit-memo';

    protected static ?string $title = 'Posted Purchase Credit Memo';

    public PostedPurchaseCreditMemo $record;

    public function mount(PostedPurchaseCreditMemo|int|string $record): void
    {
        if ($record instanceof PostedPurchaseCreditMemo) {
            $this->record = $record->load(['lines', 'vendor', 'reasonCode', 'location']);

            return;
        }

        $this->record = PostedPurchaseCreditMemo::query()
            ->with(['lines', 'vendor', 'reasonCode', 'location'])
            ->findOrFail($record);
    }

    public function getHeading(): string
    {
        return 'Posted Purchase Credit Memo '.$this->record->document_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Back to Posted Purchase Credit Memos')
                ->color('gray')
                ->url(PostedPurchaseCreditMemoResource::getUrl('index')),
            Action::make('edit')
                ->label('Edit')
                ->icon('heroicon-o-pencil-square')
                ->url(PostedPurchaseCreditMemoResource::getUrl('edit', ['record' => $this->record])),
        ];
    }

    public function getPaymentApplicationsProperty(): Collection
    {
        return PaymentApplication::query()
            ->with(['payment'])
            ->active()
            ->forDocument('PURCHASE_CREDIT_MEMO', $this->record->id)
            ->latest('applied_at')
            ->get()
            ->map(function (PaymentApplication $application): array {
                return [
                    'applied_at' => $application->applied_at,
                    'source_type' => 'Payment',
                    'status' => 'Payment Applied',
                    'status_color' => 'emerald',
                    'source_document' => $application->payment?->payment_number,
                    'payment_number' => $application->payment?->payment_number,
                    'reference' => $application->payment?->external_reference ?: $application->payment?->memo,
                    'amount_applied' => (float) $application->amount_applied,
                    'document_remaining_after' => null,
                    'source_url' => $application->payment
                        ? PaymentResource::getUrl('view', ['record' => $application->payment])
                        : null,
                ];
            });
    }

    public function getAppliedInvoicesProperty(): Collection
    {
        $ledgerEntry = VendorLedgerEntry::query()
            ->where('source_type', PostedPurchaseCreditMemo::class)
            ->where('source_id', $this->record->id)
            ->first();

        return collect($ledgerEntry?->applied_to_entries ?? []);
    }
}
