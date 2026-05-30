<?php

namespace App\Filament\Resources\PurchaseOrders\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Filament\Resources\PurchaseOrders\PurchaseOrderResource;
use App\Models\PostedPurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Services\Print\PostedPurchaseInvoicePrintService;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Services\Purchase\PurchaseOrderService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            Action::make('post_receipt')
                ->label('Post Receipt')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::PARTIALLY_RECEIVED], true))
                ->requiresConfirmation()
                ->action(function (PurchaseOrder $record, PurchaseOrderService $purchaseOrderService): void {
                    $purchaseOrderService->postReceipt($record);
                    Notification::make()->title('Receipt posted')->success()->send();
                }),
            Action::make('create_purchase_invoice')
                ->label('Create Purchase Invoice')
                ->icon('heroicon-o-document-check')
                ->color('primary')
                ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [PurchaseOrderStatus::RECEIVED, PurchaseOrderStatus::PARTIALLY_RECEIVED], true))
                ->requiresConfirmation()
                ->action(function (PurchaseOrder $record, PurchaseInvoiceService $purchaseInvoiceService) {
                    try {
                        $invoice = $purchaseInvoiceService->createFromOrder($record);
                        Notification::make()->title('Purchase Invoice Created')->success()->send();

                        return redirect(PurchaseInvoiceResource::getUrl('edit', ['record' => $invoice]));
                    } catch (\RuntimeException $exception) {
                        Notification::make()->title($exception->getMessage())->warning()->send();

                        return null;
                    }
                }),
            Action::make('post_and_invoice')
                ->label('Post + Invoice')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::PARTIALLY_RECEIVED, PurchaseOrderStatus::RECEIVED], true))
                ->requiresConfirmation()
                ->action(function (PurchaseOrder $record, PurchaseInvoiceService $purchaseInvoiceService) {
                    app(PurchaseOrderService::class)->postReceipt($record);
                    $record->refresh();

                    try {
                        $invoice = $purchaseInvoiceService->createFromOrder($record);
                        $postedInvoice = $purchaseInvoiceService->post($invoice);
                        Notification::make()->title('Receipt and Invoice Posted')->success()->send();

                        return redirect(PurchaseOrderResource::getUrl('archived', [
                            'tableSearch' => $record->order_number,
                        ]));
                    } catch (\RuntimeException $exception) {
                        Notification::make()->title($exception->getMessage())->warning()->send();

                        return null;
                    }
                }),
            Action::make('print_purchase_invoice')
                ->label('Purchase Invoice (PI)')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function (PurchaseOrder $record) {
                    $postedInvoice = PostedPurchaseInvoice::query()
                        ->where('order_id', $record->id)
                        ->latest('id')
                        ->first();

                    if (! $postedInvoice) {
                        Notification::make()
                            ->title('No posted purchase invoice found for this order.')
                            ->warning()
                            ->send();

                        return null;
                    }

                    return response()->streamDownload(
                        fn () => print (app(PostedPurchaseInvoicePrintService::class)->generatePurchaseInvoice($postedInvoice)->output()),
                        $postedInvoice->document_number.'_PI.pdf'
                    );
                }),
        ];
    }
}
