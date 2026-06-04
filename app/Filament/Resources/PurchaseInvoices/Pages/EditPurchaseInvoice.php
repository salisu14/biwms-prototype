<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Enums\ApprovalStatus;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use App\Services\Purchase\PurchaseInvoiceService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Number;

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $vendor = $record->vendor_name ?: ($record->vendor?->vendor_name ?? 'Unknown Vendor');
        $amount = Number::currency((float) $record->grand_total, $record->currency_code ?: config('app.default_currency', 'USD'));

        return ($record->document_number ?? 'Purchase Invoice')
            .' • '.$vendor
            .' • '.$amount;
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();
        $location = $record->location?->code
            ? "{$record->location->code} - {$record->location->name}"
            : ($record->location?->name ?? 'Unknown Location');

        return trim(implode(' • ', array_filter([
            $record->order_number ?: 'No purchase order',
            $location,
            $record->due_date?->format('d/m/Y') ?: 'No due date',
        ])));
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();
        $vendor = $record->vendor_name ?: ($record->vendor?->vendor_name ?? 'Unknown Vendor');

        return ($record->document_number ?? 'Purchase Invoice').' - '.$vendor;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
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
