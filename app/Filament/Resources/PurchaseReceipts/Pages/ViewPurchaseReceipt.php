<?php

namespace App\Filament\Resources\PurchaseReceipts\Pages;

use App\Filament\Resources\PurchaseReceipts\PurchaseReceiptResource;
use App\Models\PurchaseReceipt;
use App\Services\Print\PurchaseDocumentPrintService;
use App\Services\Purchase\PurchaseReceiptLinePrefillService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseReceipt extends ViewRecord
{
    protected static string $resource = PurchaseReceiptResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $vendor = $record->vendor?->vendor_name ?: $record->buy_from_vendor_name ?: 'Unknown Vendor';

        return ($record->document_number ?? 'Purchase Receipt')
            .' • '.$vendor
            .' • '.($record->posted ? 'Posted' : 'Open');
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();
        $location = $record->receivingLocation?->code
            ? "{$record->receivingLocation->code} - {$record->receivingLocation->name}"
            : ($record->receivingLocation?->name ?? 'Unknown Location');

        return ($record->purchase_order_no ?? 'No purchase order')
            .' • '.$location
            .' • '.($record->actual_receipt_date?->format('d/m/Y') ?? 'Pending');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();
        $vendor = $record->vendor?->vendor_name ?: $record->buy_from_vendor_name ?: 'Unknown Vendor';

        return ($record->document_number ?? 'Purchase Receipt').' - '.$vendor;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('prefillLines')
                ->label('Get PO Lines')
                ->icon('heroicon-o-document-duplicate')
                ->color('info')
                ->requiresConfirmation()
                ->visible(fn (PurchaseReceipt $record): bool => ! $record->posted && $record->purchase_order_id !== null && ! $record->lines()->exists())
                ->action(function (PurchaseReceipt $record): void {
                    $createdLines = app(PurchaseReceiptLinePrefillService::class)->prefillFromPurchaseOrder($record);

                    Notification::make()
                        ->title($createdLines > 0 ? 'Receipt lines copied from purchase order' : 'No remaining purchase order lines to copy')
                        ->body($createdLines > 0
                            ? "{$createdLines} line(s) were added from the purchase order."
                            : 'All available purchase order quantities may already be fully received.')
                        ->{$createdLines > 0 ? 'success' : 'warning'}()
                        ->send();
                }),
            Action::make('post')
                ->label('Post Receipt')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (PurchaseReceipt $record): bool => ! $record->posted)
                ->action(function (PurchaseReceipt $record): void {
                    try {
                        $record->post((int) auth()->id());
                        Notification::make()->title('Purchase Receipt posted successfully')->success()->send();
                    } catch (\Throwable $exception) {
                        Notification::make()->title('Unable to post receipt')->body($exception->getMessage())->danger()->send();
                    }
                }),
            Action::make('print')
                ->label('Print Receipt')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn (PurchaseReceipt $record): bool => filled($record->document_number))
                ->action(function (PurchaseReceipt $record, PurchaseDocumentPrintService $service) {
                    return response()->streamDownload(
                        fn () => print ($service->generatePurchaseReceipt($record)->output()),
                        "{$record->document_number}.pdf"
                    );
                }),
            Action::make('printThermal80')
                ->label('Print 80mm')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn (PurchaseReceipt $record): bool => filled($record->document_number))
                ->action(function (PurchaseReceipt $record, PurchaseDocumentPrintService $service) {
                    return response()->streamDownload(
                        fn () => print ($service->generatePurchaseReceiptThermal80mm($record)->output()),
                        "{$record->document_number}-80mm.pdf"
                    );
                }),
            Action::make('printThermal58')
                ->label('Print 58mm')
                ->icon('heroicon-o-printer')
                ->color('gray')
                ->visible(fn (PurchaseReceipt $record): bool => filled($record->document_number))
                ->action(function (PurchaseReceipt $record, PurchaseDocumentPrintService $service) {
                    return response()->streamDownload(
                        fn () => print ($service->generatePurchaseReceiptThermal58mm($record)->output()),
                        "{$record->document_number}-58mm.pdf"
                    );
                }),
        ];
    }
}
