<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Enums\ApprovalStatus;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Services\Purchase\PurchaseInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseInvoice extends ViewRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check')
                ->color('success')
                ->visible(fn (PurchaseInvoice $record): bool => $record->status === ApprovalStatus::PENDING)
                ->action(function (PurchaseInvoice $record): void {
                    $record->update([
                        'status' => ApprovalStatus::APPROVED,
                        'approved_by' => auth()->id(),
                        'approved_at' => now(),
                    ]);
                    Notification::make()->title('Purchase Invoice Approved')->success()->send();
                }),
            Action::make('reject')
                ->label('Reject')
                ->icon('heroicon-o-x-mark')
                ->color('danger')
                ->visible(fn (PurchaseInvoice $record): bool => $record->status === ApprovalStatus::PENDING)
                ->action(function (PurchaseInvoice $record): void {
                    $record->update([
                        'status' => ApprovalStatus::REJECTED,
                        'rejected_by' => auth()->id(),
                        'rejected_at' => now(),
                    ]);
                    Notification::make()->title('Purchase Invoice Rejected')->success()->send();
                }),
            Action::make('post')
                ->label('Post')
                ->icon('heroicon-o-check-badge')
                ->color('primary')
                ->requiresConfirmation()
                ->visible(fn (PurchaseInvoice $record): bool => $record->status === ApprovalStatus::APPROVED)
                ->action(function (PurchaseInvoice $record, PurchaseInvoiceService $purchaseInvoiceService) {
                    $posted = $purchaseInvoiceService->post($record);
                    Notification::make()->title('Purchase Invoice Posted')->success()->send();

                    return redirect(PurchaseInvoiceResource::getUrl('posted', ['tableSearch' => $posted->document_number]));
                }),
        ];
    }
}
