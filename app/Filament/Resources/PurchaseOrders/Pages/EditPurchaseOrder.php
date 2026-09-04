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
use App\Support\Filament\PostingFailureNotifier;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditPurchaseOrder extends EditRecord
{
    protected static string $resource = PurchaseOrderResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->order_number ?? 'Purchase Order')
            .' • Scope '.($record->vendor?->vendor_name ?? $record->vendor_name ?? 'Unknown Vendor')
            .' • Attribute '.number_format((float) $record->grand_total, 2);
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->order_type?->value ?? 'Order')
            .' • '.($record->location?->code ? "{$record->location->code} - {$record->location->name}" : ($record->location?->name ?? 'Unknown Location'))
            .' • '.($record->status?->value ?? 'Unknown Status');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->order_number ? "{$record->order_number} - ".($record->vendor?->vendor_name ?? $record->vendor_name ?? 'Vendor') : 'Purchase Order';
    }

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
                    try {
                        $purchaseOrderService->postReceipt($record);
                        Notification::make()->title('Receipt posted')->success()->send();
                    } catch (Throwable $exception) {
                        PostingFailureNotifier::notify($exception, 'Purchase receipt was not posted', [
                            'purchase_order_id' => $record->id,
                            'order_number' => $record->order_number,
                        ]);
                    }
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
                    } catch (Throwable $exception) {
                        PostingFailureNotifier::notify($exception, 'Purchase Invoice was not created', [
                            'purchase_order_id' => $record->id,
                            'order_number' => $record->order_number,
                        ]);

                        return null;
                    }
                }),
            Action::make('post_and_invoice')
                ->label('Post + Invoice')
                ->icon('heroicon-o-bolt')
                ->color('primary')
                ->visible(fn (PurchaseOrder $record): bool => in_array($record->status, [PurchaseOrderStatus::APPROVED, PurchaseOrderStatus::PARTIALLY_RECEIVED, PurchaseOrderStatus::RECEIVED], true))
                ->requiresConfirmation()
                ->action(function (PurchaseOrder $record) {
                    try {
                        app(PurchaseOrderService::class)->postAndInvoice($record);
                        Notification::make()->title('Receipt and Invoice Posted')->success()->send();

                        return redirect(PurchaseOrderResource::getUrl('archived', [
                            'tableSearch' => $record->order_number,
                        ]));
                    } catch (Throwable $exception) {
                        PostingFailureNotifier::notify($exception, 'Purchase Order was not posted and invoiced', [
                            'purchase_order_id' => $record->id,
                            'order_number' => $record->order_number,
                        ]);

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
