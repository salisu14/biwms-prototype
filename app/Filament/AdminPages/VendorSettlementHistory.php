<?php

declare(strict_types=1);

namespace App\Filament\AdminPages;

use App\Filament\Pages\VendorSettlementHistoryPage;

class VendorSettlementHistory extends VendorSettlementHistoryPage
{
    protected static ?string $slug = 'vendor-settlement-history-admin';

    protected static bool $shouldRegisterNavigation = false;
}
