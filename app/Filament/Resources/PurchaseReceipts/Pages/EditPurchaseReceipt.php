<?php

namespace App\Filament\Resources\PurchaseReceipts\Pages;

use App\Filament\Resources\PurchaseReceipts\PurchaseReceiptResource;
use App\Models\PurchaseReceipt;
use App\Services\Purchase\PurchaseReceiptLinePrefillService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPurchaseReceipt extends EditRecord
{
    protected static string $resource = PurchaseReceiptResource::class;

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
            ViewAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
