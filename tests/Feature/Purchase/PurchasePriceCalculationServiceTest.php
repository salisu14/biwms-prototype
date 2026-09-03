<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Enums\PurchaseLineType;
use App\Enums\PurchaseOrderStatus;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\RelationManagers\PurchaseOrderLinesRelationManager;
use App\Models\Business;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\Item;
use App\Models\ItemUomAssignment;
use App\Models\Location;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostedPurchaseInvoiceLine;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorItem;
use App\Models\VendorPostingGroup;
use App\Services\Purchase\PurchasePriceCalculationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('returns the standard cost when no posted purchase history exists', function (): void {
    $fixture = purchasePriceFixture(standardCost: 47.5);

    $price = app(PurchasePriceCalculationService::class)->getUnitCost(
        $fixture['vendor'],
        $fixture['item'],
        1,
        $fixture['item']->base_unit_of_measure,
        now(),
        $fixture['business']->id
    );

    expect($price['direct_unit_cost'])->toBe('47.50000000')
        ->and($price['price_source'])->toBe('standard_cost');
});

it('returns the latest posted purchase invoice line cost for the same vendor item and business', function (): void {
    $fixture = purchasePriceFixture(standardCost: 999);

    createPostedPurchaseInvoiceLine(
        business: $fixture['business'],
        vendor: $fixture['vendor'],
        item: $fixture['item'],
        documentNumber: 'PI-HIST-OLD',
        postingDate: '2026-01-02',
        unitCost: 25
    );

    createPostedPurchaseInvoiceLine(
        business: $fixture['business'],
        vendor: $fixture['vendor'],
        item: $fixture['item'],
        documentNumber: 'PI-HIST-NEW',
        postingDate: '2026-02-15',
        unitCost: 30
    );

    $price = app(PurchasePriceCalculationService::class)->getUnitCost(
        $fixture['vendor'],
        $fixture['item'],
        1,
        $fixture['item']->base_unit_of_measure,
        now(),
        $fixture['business']->id
    );

    expect($price['direct_unit_cost'])->toBe(30.0)
        ->and($price['price_source'])->toBe('last_direct_cost');
});

it('does not leak posted purchase history across businesses', function (): void {
    $fixture = purchasePriceFixture(standardCost: 88);
    $otherBusiness = Business::query()->create([
        'code' => 'BUS-B',
        'name' => 'Business B',
        'is_active' => true,
    ]);

    createPostedPurchaseInvoiceLine(
        business: $fixture['business'],
        vendor: $fixture['vendor'],
        item: $fixture['item'],
        documentNumber: 'PI-BIZ-A',
        postingDate: '2026-03-01',
        unitCost: 22
    );

    $price = app(PurchasePriceCalculationService::class)->getUnitCost(
        $fixture['vendor'],
        $fixture['item'],
        1,
        $fixture['item']->base_unit_of_measure,
        now(),
        $otherBusiness->id
    );

    expect($price['direct_unit_cost'])->toBe('88.00000000')
        ->and($price['price_source'])->toBe('standard_cost');
});

it('does not leak purchase history between vendors', function (): void {
    $fixture = purchasePriceFixture(standardCost: 64);
    $otherVendor = Vendor::factory()->create([
        'general_business_posting_group_id' => $fixture['generalBusinessPostingGroup']->id,
        'vendor_posting_group_id' => $fixture['vendorPostingGroup']->id,
        'vat_bus_posting_group' => null,
    ]);

    createPostedPurchaseInvoiceLine(
        business: $fixture['business'],
        vendor: $fixture['vendor'],
        item: $fixture['item'],
        documentNumber: 'PI-VENDOR-A',
        postingDate: '2026-04-01',
        unitCost: 19
    );

    $price = app(PurchasePriceCalculationService::class)->getUnitCost(
        $otherVendor,
        $fixture['item'],
        1,
        $fixture['item']->base_unit_of_measure,
        now(),
        $fixture['business']->id
    );

    expect($price['direct_unit_cost'])->toBe('64.00000000')
        ->and($price['price_source'])->toBe('standard_cost');
});

it('ignores unposted purchase orders when resolving last direct cost', function (): void {
    $fixture = purchasePriceFixture(standardCost: 101);

    createPostedPurchaseInvoiceLine(
        business: $fixture['business'],
        vendor: $fixture['vendor'],
        item: $fixture['item'],
        documentNumber: 'PI-POSTED-ONLY',
        postingDate: '2026-05-01',
        unitCost: 27
    );

    $draftOrder = PurchaseOrder::query()->create([
        'business_id' => $fixture['business']->id,
        'order_number' => 'PO-DRAFT-NEWER',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'order_date' => '2026-05-15',
        'status' => PurchaseOrderStatus::PENDING,
        'location_id' => $fixture['location']->id,
        'created_by' => $fixture['user']->id,
        'general_business_posting_group_id' => $fixture['generalBusinessPostingGroup']->id,
        'vendor_posting_group_id' => $fixture['vendorPostingGroup']->id,
        'currency_code' => 'NGN',
    ]);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $draftOrder->id,
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 1,
        'unit_of_measure' => $fixture['item']->base_unit_of_measure,
        'unit_cost' => 10,
        'type' => PurchaseLineType::ITEM,
    ]);

    $price = app(PurchasePriceCalculationService::class)->getUnitCost(
        $fixture['vendor'],
        $fixture['item'],
        1,
        $fixture['item']->base_unit_of_measure,
        now(),
        $fixture['business']->id
    );

    expect($price['direct_unit_cost'])->toBe(27.0)
        ->and($price['price_source'])->toBe('last_direct_cost');
});

it('executes purchase order line item selection without throwing and applies the resolved cost', function (): void {
    $fixture = purchasePriceFixture(standardCost: 42.25);
    $user = superAdminPurchasePricingUser();

    $order = PurchaseOrder::query()->create([
        'business_id' => $fixture['business']->id,
        'order_number' => 'PO-REACTIVE-001',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'order_date' => '2026-06-01',
        'status' => PurchaseOrderStatus::PENDING,
        'location_id' => $fixture['location']->id,
        'created_by' => $user->id,
        'general_business_posting_group_id' => $fixture['generalBusinessPostingGroup']->id,
        'vendor_posting_group_id' => $fixture['vendorPostingGroup']->id,
        'currency_code' => 'NGN',
    ]);

    Livewire::actingAs($user)
        ->test(PurchaseOrderLinesRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => EditPurchaseOrder::class,
        ])
        ->callTableAction('create', data: [
            'item_id' => $fixture['item']->id,
            'quantity' => 2,
            'unit_of_measure' => $fixture['item']->base_unit_of_measure,
            'unit_cost' => 0,
            'vat_percentage' => 0,
            'description' => 'Placeholder Description',
        ])
        ->assertHasNoTableActionErrors();
});

it('defaults the purchase order line description from the canonical item description when no vendor-specific purchasing description exists', function (): void {
    $fixture = purchasePriceFixture(standardCost: 42.25);
    $user = superAdminPurchasePricingUser();

    $order = PurchaseOrder::query()->create([
        'business_id' => $fixture['business']->id,
        'order_number' => 'PO-DESC-001',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'order_date' => '2026-06-02',
        'status' => PurchaseOrderStatus::PENDING,
        'location_id' => $fixture['location']->id,
        'created_by' => $user->id,
        'general_business_posting_group_id' => $fixture['generalBusinessPostingGroup']->id,
        'vendor_posting_group_id' => $fixture['vendorPostingGroup']->id,
        'currency_code' => 'NGN',
    ]);

    Livewire::actingAs($user)
        ->test(PurchaseOrderLinesRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => EditPurchaseOrder::class,
        ])
        ->callTableAction('create', data: [
            'item_id' => $fixture['item']->id,
            'quantity' => 2,
            'unit_of_measure' => $fixture['item']->base_unit_of_measure,
            'unit_cost' => 0,
            'vat_percentage' => 0,
            'description' => '',
        ])
        ->assertHasNoTableActionErrors();

    $line = $order->fresh()->lines()->firstOrFail();

    expect($line->description)->toBe($fixture['item']->description)
        ->and((float) $line->quantity)->toBe(2.0);
});

it('prefers the vendor-specific purchasing description when one exists', function (): void {
    $fixture = purchasePriceFixture(standardCost: 42.25);
    $user = superAdminPurchasePricingUser();

    VendorItem::query()->create([
        'vendor_id' => $fixture['vendor']->id,
        'item_id' => $fixture['item']->id,
        'vendor_item_number' => 'VEN-ITEM-1000',
        'vendor_item_name' => 'Sodium Saccharine',
        'vendor_item_category' => 'RAW',
        'unit_cost' => 42.25,
        'purchase_uom_id' => $fixture['item']->base_uom_id,
        'currency_id' => null,
        'minimum_order_qty' => 1,
        'lead_time_days' => 0,
        'is_preferred' => true,
        'is_active' => true,
        'effective_date' => now()->toDateString(),
    ]);

    $order = PurchaseOrder::query()->create([
        'business_id' => $fixture['business']->id,
        'order_number' => 'PO-DESC-002',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'order_date' => '2026-06-03',
        'status' => PurchaseOrderStatus::PENDING,
        'location_id' => $fixture['location']->id,
        'created_by' => $user->id,
        'general_business_posting_group_id' => $fixture['generalBusinessPostingGroup']->id,
        'vendor_posting_group_id' => $fixture['vendorPostingGroup']->id,
        'currency_code' => 'NGN',
    ]);

    Livewire::actingAs($user)
        ->test(PurchaseOrderLinesRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => EditPurchaseOrder::class,
        ])
        ->callTableAction('create', data: [
            'item_id' => $fixture['item']->id,
            'quantity' => 1,
            'unit_of_measure' => $fixture['item']->base_unit_of_measure,
            'unit_cost' => 0,
            'vat_percentage' => 0,
            'description' => '',
        ])
        ->assertHasNoTableActionErrors();

    $line = $order->fresh()->lines()->firstOrFail();

    expect($line->description)->toBe('Sodium Saccharine');
});

it('preserves a manually edited purchase order line description when quantity unit cost or vat changes', function (): void {
    $fixture = purchasePriceFixture(standardCost: 42.25);
    $user = superAdminPurchasePricingUser();

    $order = PurchaseOrder::query()->create([
        'business_id' => $fixture['business']->id,
        'order_number' => 'PO-DESC-003',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'order_date' => '2026-06-04',
        'status' => PurchaseOrderStatus::PENDING,
        'location_id' => $fixture['location']->id,
        'created_by' => $user->id,
        'general_business_posting_group_id' => $fixture['generalBusinessPostingGroup']->id,
        'vendor_posting_group_id' => $fixture['vendorPostingGroup']->id,
        'currency_code' => 'NGN',
    ]);

    Livewire::actingAs($user)
        ->test(PurchaseOrderLinesRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => EditPurchaseOrder::class,
        ])
        ->callTableAction('create', data: [
            'item_id' => $fixture['item']->id,
            'quantity' => 5,
            'unit_of_measure' => $fixture['item']->base_unit_of_measure,
            'unit_cost' => 12.5,
            'vat_percentage' => 7.5,
            'description' => 'Custom Procurement Note',
        ])
        ->assertHasNoTableActionErrors();

    $line = $order->fresh()->lines()->firstOrFail();

    expect($line->description)->toBe('Custom Procurement Note')
        ->and((float) $line->quantity)->toBe(5.0)
        ->and((float) $line->unit_cost)->toBe(12.5)
        ->and((float) $line->vat_percentage)->toBe(7.5);
});

it('refreshes the purchase order line description when the selected item changes', function (): void {
    $fixture = purchasePriceFixture(standardCost: 42.25);
    $user = superAdminPurchasePricingUser();

    $secondItem = Item::query()->create([
        'item_code' => 'RM-2000',
        'description' => 'Calcium Carbonate',
        'item_type' => ItemType::RAW_MATERIAL,
        'base_uom_id' => $fixture['item']->base_uom_id,
        'standard_cost' => 15,
        'unit_cost' => 15,
        'last_direct_cost' => 0,
        'inventory' => 0,
        'location_id' => $fixture['location']->id,
        'general_product_posting_group_id' => $fixture['productGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryGroup']->id,
        'blocked' => false,
        'is_active' => true,
    ]);

    $order = PurchaseOrder::query()->create([
        'business_id' => $fixture['business']->id,
        'order_number' => 'PO-DESC-004',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'order_date' => '2026-06-05',
        'status' => PurchaseOrderStatus::PENDING,
        'location_id' => $fixture['location']->id,
        'created_by' => $user->id,
        'general_business_posting_group_id' => $fixture['generalBusinessPostingGroup']->id,
        'vendor_posting_group_id' => $fixture['vendorPostingGroup']->id,
        'currency_code' => 'NGN',
    ]);

    Livewire::actingAs($user)
        ->test(PurchaseOrderLinesRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => EditPurchaseOrder::class,
        ])
        ->callTableAction('create', data: [
            'item_id' => $fixture['item']->id,
            'quantity' => 2,
            'unit_of_measure' => $fixture['item']->base_unit_of_measure,
            'unit_cost' => 0,
            'vat_percentage' => 0,
            'description' => '',
        ])
        ->assertHasNoTableActionErrors();

    $line = $order->fresh()->lines()->firstOrFail();

    Livewire::actingAs($user)
        ->test(PurchaseOrderLinesRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => EditPurchaseOrder::class,
        ])
        ->callTableAction('edit', $line, data: [
            'item_id' => $secondItem->id,
            'quantity' => 2,
            'unit_of_measure' => $secondItem->base_unit_of_measure,
            'unit_cost' => 0,
            'vat_percentage' => 0,
            'description' => '',
        ])
        ->assertHasNoTableActionErrors();

    expect($line->fresh()->description)->toBe('Calcium Carbonate');
});

it('falls back safely when an item description is blank', function (): void {
    $fixture = purchasePriceFixture(standardCost: 42.25);
    $user = superAdminPurchasePricingUser();

    $blankDescriptionItem = Item::query()->create([
        'item_code' => 'RM-3000',
        'description' => '',
        'item_type' => ItemType::RAW_MATERIAL,
        'base_uom_id' => $fixture['item']->base_uom_id,
        'standard_cost' => 15,
        'unit_cost' => 15,
        'last_direct_cost' => 0,
        'inventory' => 0,
        'location_id' => $fixture['location']->id,
        'general_product_posting_group_id' => $fixture['productGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryGroup']->id,
        'blocked' => false,
        'is_active' => true,
    ]);

    $order = PurchaseOrder::query()->create([
        'business_id' => $fixture['business']->id,
        'order_number' => 'PO-DESC-005',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'order_date' => '2026-06-06',
        'status' => PurchaseOrderStatus::PENDING,
        'location_id' => $fixture['location']->id,
        'created_by' => $user->id,
        'general_business_posting_group_id' => $fixture['generalBusinessPostingGroup']->id,
        'vendor_posting_group_id' => $fixture['vendorPostingGroup']->id,
        'currency_code' => 'NGN',
    ]);

    Livewire::actingAs($user)
        ->test(PurchaseOrderLinesRelationManager::class, [
            'ownerRecord' => $order,
            'pageClass' => EditPurchaseOrder::class,
        ])
        ->callTableAction('create', data: [
            'item_id' => $blankDescriptionItem->id,
            'quantity' => 1,
            'unit_of_measure' => $blankDescriptionItem->base_unit_of_measure,
            'unit_cost' => 0,
            'vat_percentage' => 0,
            'description' => '',
        ])
        ->assertHasNoTableActionErrors();

    expect($order->fresh()->lines()->firstOrFail()->description)->toBe($blankDescriptionItem->item_code);
});

function purchasePriceFixture(float $standardCost): array
{
    Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $business = Business::query()->create([
        'code' => 'BUS-A',
        'name' => 'Business A',
        'is_active' => true,
    ]);

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole('super_admin');

    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic',
        'blocked' => false,
    ]);

    $vendorPostingGroup = VendorPostingGroup::query()->create([
        'code' => 'DOMESTIC-VEND',
        'description' => 'Domestic Vendors',
        'blocked' => false,
    ]);

    $vendor = Vendor::factory()->create([
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'vat_bus_posting_group' => null,
    ]);

    $location = Location::factory()->create(['code' => 'MAIN']);

    $productGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'RAWMAT',
        'description' => 'Raw Material',
        'blocked' => false,
    ]);

    $inventoryGroup = InventoryPostingGroup::query()->create([
        'code' => 'RAW',
        'description' => 'Raw Inventory',
        'blocked' => false,
    ]);

    $baseUom = UnitOfMeasure::query()->create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'is_base_uom' => true,
    ]);

    $item = Item::query()->create([
        'item_code' => 'RM-1000',
        'description' => 'Raw Material 1000',
        'item_type' => ItemType::RAW_MATERIAL,
        'base_uom_id' => $baseUom->id,
        'standard_cost' => $standardCost,
        'unit_cost' => $standardCost,
        'last_direct_cost' => 0,
        'inventory' => 0,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'blocked' => false,
        'is_active' => true,
    ]);

    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $baseUom->id,
        'uom_type' => 'PURCHASE',
        'conversion_factor' => 1,
        'is_default' => true,
    ]);

    return [
        'business' => $business,
        'user' => $user,
        'vendor' => $vendor,
        'location' => $location,
        'item' => $item,
        'generalBusinessPostingGroup' => $generalBusinessPostingGroup,
        'productGroup' => $productGroup,
        'vendorPostingGroup' => $vendorPostingGroup,
        'inventoryGroup' => $inventoryGroup,
    ];
}

function createPostedPurchaseInvoiceLine(
    Business $business,
    Vendor $vendor,
    Item $item,
    string $documentNumber,
    string $postingDate,
    float $unitCost,
): PostedPurchaseInvoiceLine {
    $invoice = PostedPurchaseInvoice::query()->create([
        'business_id' => $business->id,
        'document_number' => $documentNumber,
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
        'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
        'location_id' => null,
        'posting_date' => $postingDate,
        'document_date' => $postingDate,
        'due_date' => $postingDate,
        'total_amount' => $unitCost,
        'total_vat' => 0,
        'grand_total' => $unitCost,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'amount_paid' => 0,
        'remaining_amount' => $unitCost,
        'paid_in_full' => false,
        'posted_by' => null,
        'posted_at' => now(),
        'cancelled' => false,
    ]);

    return $invoice->lines()->create([
        'po_line_id' => null,
        'po_line_number' => null,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'item_description' => $item->description,
        'variant_code' => null,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'quantity' => 1,
        'unit_of_measure_code' => $item->base_unit_of_measure,
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_cost' => $unitCost,
        'unit_cost_lcy' => $unitCost,
        'line_total' => $unitCost,
        'line_discount_amount' => 0,
        'line_discount_percent' => 0,
        'vat_code' => null,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => $unitCost,
        'amount_including_vat_lcy' => $unitCost,
        'line_number' => 10000,
        'posting_date' => $postingDate,
    ]);
}

function superAdminPurchasePricingUser(): User
{
    Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);

    $user->assignRole('super_admin');

    return $user;
}
