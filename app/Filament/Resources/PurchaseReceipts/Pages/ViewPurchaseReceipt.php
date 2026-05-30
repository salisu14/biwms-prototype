<?php

namespace App\Filament\Resources\PurchaseReceipts\Pages;

use App\Filament\Resources\PurchaseReceipts\PurchaseReceiptResource;
use App\Models\PurchaseReceipt;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewPurchaseReceipt extends ViewRecord
{
    protected static string $resource = PurchaseReceiptResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
            EditAction::make(),
        ];
    }
}
