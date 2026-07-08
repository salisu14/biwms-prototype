<?php

it('keeps priority forms on responsive breakpoint grids', function (): void {
    foreach ([
        app_path('Filament/Sales/Resources/SalesOrders/Schemas/SalesOrderForm.php'),
        app_path('Filament/Sales/Resources/SalesInvoices/Schemas/SalesInvoiceForm.php'),
        app_path('Filament/Resources/PurchaseOrders/Schemas/PurchaseOrderForm.php'),
        app_path('Filament/Resources/PurchaseInvoices/Schemas/PurchaseInvoiceForm.php'),
        app_path('Filament/Resources/ProductionOrders/Schemas/ProductionOrderForm.php'),
        app_path('Filament/Resources/Items/Schemas/ItemForm.php'),
        app_path('Filament/Resources/Roles/RoleResource.php'),
    ] as $file) {
        $contents = file_get_contents($file);

        expect($contents)
            ->toContain("'default' => 1")
            ->toContain("'md' =>");
    }
});

it('keeps priority tables compact with grouped actions or toggleable secondary columns', function (): void {
    foreach ([
        app_path('Filament/Sales/Resources/SalesOrders/Tables/SalesOrdersTable.php'),
        app_path('Filament/Sales/Resources/SalesInvoices/Tables/SalesInvoicesTable.php'),
        app_path('Filament/Resources/SalesInvoices/Tables/PostedSalesInvoicesTable.php'),
        app_path('Filament/Resources/PurchaseOrders/Tables/PurchaseOrdersTable.php'),
        app_path('Filament/Resources/PurchaseInvoices/Tables/PurchaseInvoicesTable.php'),
        app_path('Filament/Resources/ProductionOrders/Tables/ProductionOrdersTable.php'),
        app_path('Filament/Resources/Items/Tables/ItemsTable.php'),
        app_path('Filament/Pages/UserSecurity.php'),
    ] as $file) {
        $contents = file_get_contents($file);

        expect($contents)
            ->toContain('ActionGroup::make')
            ->toContain('toggleable(isToggledHiddenByDefault: true)');
    }
});
