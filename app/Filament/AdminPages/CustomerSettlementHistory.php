<?php

declare(strict_types=1);

namespace App\Filament\AdminPages;

use App\Filament\Pages\CustomerSettlementHistoryPage;

class CustomerSettlementHistory extends CustomerSettlementHistoryPage
{
    protected static ?string $slug = 'customer-settlement-history-admin';

    protected static bool $shouldRegisterNavigation = false;
}
