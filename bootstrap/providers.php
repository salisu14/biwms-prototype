<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\FactoryPanelProvider;
use App\Providers\Filament\FinancePanelProvider;
use App\Providers\Filament\HrPanelProvider;
use App\Providers\Filament\SalesPanelProvider;
use App\Providers\Filament\WarehousePanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    FinancePanelProvider::class,
    SalesPanelProvider::class,
    WarehousePanelProvider::class,
    FactoryPanelProvider::class,
    HrPanelProvider::class,
];
