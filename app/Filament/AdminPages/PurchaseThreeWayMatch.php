<?php

declare(strict_types=1);

namespace App\Filament\AdminPages;

use App\Filament\Pages\PurchaseThreeWayMatchPage;

class PurchaseThreeWayMatch extends PurchaseThreeWayMatchPage
{
    protected static ?string $slug = 'purchase-three-way-match-admin';

    protected static bool $shouldRegisterNavigation = false;
}
