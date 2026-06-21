<?php

namespace App\Filament\Pages;

use App\Enums\PurchaseOrderStatus;
use App\Models\PostedPurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseReceipt;
use Filament\Pages\Page;

class PurchaseHistory extends Page
{
    protected string $view = 'filament.pages.purchase-history';

    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-document-check';

    protected static ?string $title = 'Navigate';

    protected static ?string $navigationLabel = 'History';

    protected static string|null|\UnitEnum $navigationGroup = 'Purchases';

    public function getViewData(): array
    {
        return [
            'postedReceiptCount' => PurchaseReceipt::count(),
            'postedInvoiceCount' => PostedPurchaseInvoice::count(),
            'archivedOrderCount' => PurchaseOrder::where('status', PurchaseOrderStatus::CLOSED->value)->count(),
        ];
    }
}
