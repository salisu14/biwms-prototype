<?php

namespace App\Filament\Resources\PurchaseInvoices\Pages;

use App\Enums\ApprovalStatus;
use App\Filament\Resources\PurchaseInvoices\PurchaseInvoiceResource;
use App\Models\PurchaseInvoice;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPurchaseInvoices extends ListRecords
{
    protected static string $resource = PurchaseInvoiceResource::class;

    public function getTitle(): string
    {
        return 'Purchase Invoices';
    }

    protected function getTableQuery(): Builder
    {
        return PurchaseInvoice::query()
            ->where(function (Builder $query): void {
                $query->whereNull('posted_at')
                    ->orWhere('status', '!=', ApprovalStatus::POSTED->value);
            })
            ->latest('id');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('posted_purchase_invoices')
                ->label('Posted Purchase Invoices')
                ->icon('heroicon-o-archive-box')
                ->color('gray')
                ->url(PurchaseInvoiceResource::getUrl('posted')),
            CreateAction::make(),
        ];
    }
}
