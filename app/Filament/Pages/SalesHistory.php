<?php

namespace App\Filament\Pages;

use App\Enums\SalesOrderStatus;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesShipmentHeader;
use Filament\Pages\Page;

class SalesHistory extends Page
{
    protected string $view = 'filament.pages.sales-history';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $title = 'Navigate';

    protected static ?string $navigationLabel = 'History';

    protected static string|null|\UnitEnum $navigationGroup = 'Sales';

    public function getViewData(): array
    {
        return [
            'postedShipmentCount' => SalesShipmentHeader::count(),
            'postedInvoiceCount' => SalesInvoice::where('status', 'posted')->count(),
            'archivedOrderCount' => SalesOrder::where('status', SalesOrderStatus::CLOSED->value)->count(),
        ];
    }
}
