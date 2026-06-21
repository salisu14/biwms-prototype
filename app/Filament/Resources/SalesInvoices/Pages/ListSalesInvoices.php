<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Enums\ApprovalStatus;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Models\SalesInvoice;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSalesInvoices extends ListRecords
{
    protected static string $resource = SalesInvoiceResource::class;

    protected static ?string $title = 'Sales Invoices';

    protected function getTableQuery(): Builder
    {
        return SalesInvoice::query()
            ->whereNull('posted_at')
            ->where('status', '!=', ApprovalStatus::POSTED->value);
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('postedInvoices')
                ->label('Posted Invoices')
                ->icon('heroicon-o-document-check')
                ->url(route('filament.admin.resources.sales-invoices.posted'))
                ->visible(fn (): bool => SalesInvoiceResource::canAccessPostedInvoiceHistory()),
        ];
    }
}
