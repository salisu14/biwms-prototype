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

class EditPurchaseInvoice extends EditRecord
{
    protected static string $resource = PurchaseInvoiceResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->document_number ?? 'Purchase Invoice')
            .' • Scope '.($record->vendor_name ?? '—')
            .' • Attribute '.number_format((float) $record->grand_total, 2);
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->order_number ?? 'No purchase order')
            .' • '.($record->location?->name ?? 'Unknown Location')
            .' • '.($record->due_date?->format('d/m/Y') ?? 'No due date');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->document_number ?? 'Purchase Invoice';
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
