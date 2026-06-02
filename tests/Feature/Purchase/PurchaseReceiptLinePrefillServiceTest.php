<?php

declare(strict_types=1);

use App\Enums\PurchaseLineType;
use App\Enums\PurchaseOrderStatus;
use App\Models\Contact;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchaseReceipt;
use App\Models\User;
use App\Models\VatProductPostingGroup;
use App\Models\Vendor;
use App\Models\VendorPostingGroup;
use App\Services\Purchase\PurchaseReceiptLinePrefillService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('prefills purchase receipt lines from the remaining purchase order quantities', function (): void {
    $user = User::factory()->create();
    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic',
        'blocked' => false,
    ]);
    $vendorPostingGroup = VendorPostingGroup::factory()->create();
    $contact = Contact::query()->create([
        'name' => 'Vendor Contact',
        'full_name' => 'Vendor Contact',
        'company_name' => 'Vendor Company',
        'role' => 'vendor',
        'type' => 'company',
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'vat_bus_posting_group' => 'DOMESTIC',
    ]);
    $vendor = Vendor::factory()->create([
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'gen_bus_posting_group' => $generalBusinessPostingGroup->code,
        'vendor_posting_group' => $vendorPostingGroup->code,
        'vat_bus_posting_group' => 'DOMESTIC',
        'contact_id' => $contact->id,
    ]);
    $location = Location::factory()->create(['code' => 'MAIN']);
    $vatPostingGroup = VatProductPostingGroup::query()->create([
        'code' => 'PURCH-VAT',
        'description' => 'Purchase VAT',
    ]);
    $generalProductPostingGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'RAWMAT',
        'description' => 'Raw Material',
        'default_vat_product_posting_group_id' => $vatPostingGroup->id,
        'blocked' => false,
    ]);
    $inventoryPostingGroup = InventoryPostingGroup::query()->create([
        'code' => 'RAW',
        'description' => 'Raw Inventory',
        'blocked' => false,
    ]);
    $item = Item::factory()->create([
        'item_code' => 'ITEM-1000',
        'description' => 'Test Item',
        'unit_cost' => 25,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'vat_product_posting_group_id' => $vatPostingGroup->id,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'order_number' => 'PO-TEST-0001',
        'status' => PurchaseOrderStatus::APPROVED,
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'order_date' => now()->toDateString(),
        'location_id' => $location->id,
        'created_by' => $user->id,
    ]);

    $purchaseOrderLine = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_number' => 10000,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 10,
        'received_quantity' => 4,
        'unit_of_measure' => 'PCS',
        'unit_cost' => 25,
        'type' => PurchaseLineType::ITEM,
    ]);

    $purchaseReceipt = PurchaseReceipt::query()->create([
        'document_number' => 'PR-TEST-0001',
        'vendor_id' => $vendor->id,
        'purchase_order_id' => $purchaseOrder->id,
        'purchase_order_no' => $purchaseOrder->order_number,
        'buy_from_vendor_name' => $vendor->vendor_name,
        'receiving_location_id' => $location->id,
        'location_code' => $location->code,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'requested_receipt_date' => now()->addDay()->toDateString(),
        'promised_receipt_date' => now()->addDays(2)->toDateString(),
        'shortcut_dimension_1_code' => 'ADMIN',
        'shortcut_dimension_2_code' => 'HQ',
    ]);

    $createdLines = app(PurchaseReceiptLinePrefillService::class)->prefillFromPurchaseOrder($purchaseReceipt);

    expect($createdLines)->toBe(1);

    $receiptLine = $purchaseReceipt->lines()->first();

    expect($receiptLine)->not->toBeNull()
        ->and((float) $receiptLine->quantity)->toBe(6.0)
        ->and((float) $receiptLine->qty_to_receive)->toBe(6.0)
        ->and($receiptLine->no)->toBe($item->item_code)
        ->and($receiptLine->purchase_order_line_id)->toBe($purchaseOrderLine->id)
        ->and($receiptLine->location_code)->toBe($location->code)
        ->and($receiptLine->requested_receipt_date?->toDateString())->toBe(now()->addDay()->toDateString())
        ->and($receiptLine->promised_receipt_date?->toDateString())->toBe(now()->addDays(2)->toDateString())
        ->and($receiptLine->shortcut_dimension_1_code)->toBe('ADMIN')
        ->and($receiptLine->shortcut_dimension_2_code)->toBe('HQ')
        ->and($receiptLine->vat_prod_posting_group)->toBe('PURCH-VAT')
        ->and((float) $receiptLine->vat_base_amount)->toBe(150.0);
});

it('does not duplicate receipt lines when prefill runs more than once', function (): void {
    $user = User::factory()->create();
    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'EXPORT',
        'description' => 'Export',
        'blocked' => false,
    ]);
    $vendorPostingGroup = VendorPostingGroup::factory()->create();
    $contact = Contact::query()->create([
        'name' => 'Vendor Contact 2',
        'full_name' => 'Vendor Contact 2',
        'company_name' => 'Vendor Company 2',
        'role' => 'vendor',
        'type' => 'company',
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'vat_bus_posting_group' => 'DOMESTIC',
    ]);
    $vendor = Vendor::factory()->create([
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'gen_bus_posting_group' => $generalBusinessPostingGroup->code,
        'vendor_posting_group' => $vendorPostingGroup->code,
        'vat_bus_posting_group' => 'DOMESTIC',
        'contact_id' => $contact->id,
    ]);
    $location = Location::factory()->create(['code' => 'MAIN']);
    $generalProductPostingGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'SERVICE',
        'description' => 'Service',
        'blocked' => false,
    ]);
    $inventoryPostingGroup = InventoryPostingGroup::query()->create([
        'code' => 'FINISHED',
        'description' => 'Finished Inventory',
        'blocked' => false,
    ]);
    $item = Item::factory()->create([
        'item_code' => 'ITEM-2000',
        'description' => 'Test Item 2',
        'unit_cost' => 10,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);

    $purchaseOrder = PurchaseOrder::query()->create([
        'order_number' => 'PO-TEST-0002',
        'status' => PurchaseOrderStatus::APPROVED,
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'order_date' => now()->toDateString(),
        'location_id' => $location->id,
        'created_by' => $user->id,
    ]);

    PurchaseOrderLine::query()->create([
        'purchase_order_id' => $purchaseOrder->id,
        'line_number' => 10000,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 3,
        'received_quantity' => 0,
        'unit_of_measure' => 'PCS',
        'unit_cost' => 10,
        'type' => PurchaseLineType::ITEM,
    ]);

    $purchaseReceipt = PurchaseReceipt::query()->create([
        'document_number' => 'PR-TEST-0002',
        'vendor_id' => $vendor->id,
        'purchase_order_id' => $purchaseOrder->id,
        'purchase_order_no' => $purchaseOrder->order_number,
        'buy_from_vendor_name' => $vendor->vendor_name,
        'receiving_location_id' => $location->id,
        'location_code' => $location->code,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
    ]);

    $service = app(PurchaseReceiptLinePrefillService::class);

    expect($service->prefillFromPurchaseOrder($purchaseReceipt))->toBe(1)
        ->and($service->prefillFromPurchaseOrder($purchaseReceipt->fresh()))->toBe(0)
        ->and($purchaseReceipt->fresh()->lines()->count())->toBe(1);
});
