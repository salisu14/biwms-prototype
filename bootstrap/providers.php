<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\FactoryPanelProvider;
use App\Providers\Filament\FinancePanelProvider;
use App\Providers\Filament\HrPanelProvider;
use App\Providers\Filament\ProcurementPanelProvider;
use App\Providers\Filament\ProjectPanelProvider;
use App\Providers\Filament\SalesPanelProvider;
use App\Providers\Filament\ServicePanelProvider;
use App\Providers\Filament\WarehousePanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    FinancePanelProvider::class,
    ProcurementPanelProvider::class,
    ProjectPanelProvider::class,
    SalesPanelProvider::class,
    ServicePanelProvider::class,
    WarehousePanelProvider::class,
    FactoryPanelProvider::class,
    HrPanelProvider::class,
];
