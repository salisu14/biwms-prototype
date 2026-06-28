<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Filament\Resources\SalesInvoices\Tables\PostedSalesInvoicesTable;
use App\Models\PostedSalesInvoice;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PostedSalesInvoices extends ListRecords
{
    protected static string $resource = SalesInvoiceResource::class;

    protected static ?string $title = 'Posted Sales Invoices';

    protected static ?string $navigationLabel = 'Posted Invoices';

    public static function canAccess(array $parameters = []): bool
    {
        return SalesInvoiceResource::canAccessPostedInvoiceHistory();
    }

    // This filters to only posted invoices
    protected function getTableQuery(): Builder
    {
        return PostedSalesInvoice::query()
            ->with(['customer', 'location', 'salesOrder'])
            ->whereNotNull('posted_at')
            ->latest('posted_at');
    }

    public function table(Table $table): Table
    {
        return PostedSalesInvoicesTable::configure($table);
    }

    // Remove create button
    protected function getHeaderActions(): array
    {
        return [
        ];
    }
}
