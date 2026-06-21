<?php

use App\Enums\PurchaseOrderStatus;
use App\Models\Contact;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\Item;
use App\Models\Location;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPostingGroup;
use App\Services\Purchase\PurchaseOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

function makePurchaseOrderWithOneLine(float $quantity = 10): array
{
    $user = User::factory()->create();
    $location = Location::factory()->create();

    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'DOM-T',
        'description' => 'Domestic Test',
        'blocked' => false,
    ]);

    $vendorPostingGroup = VendorPostingGroup::query()->create([
        'code' => 'VPG-T',
        'description' => 'Vendor Test Group',
        'blocked' => false,
    ]);

    $contact = Contact::query()->create([
        'name' => 'Vendor Contact',
        'full_name' => 'Vendor Contact',
        'type' => 'person',
        'role' => 'vendor',
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
    ]);

    $vendor = Vendor::query()->create([
        'vendor_code' => 'V-T-0001',
        'vendor_name' => 'Vendor Test',
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'contact_id' => $contact->id,
        'is_active' => true,
        'blocked' => false,
    ]);

    DB::table('general_product_posting_groups')->insert([
        'code' => 'FG',
        'description' => 'Finished Goods',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('inventory_posting_groups')->insert([
        'code' => 'INV',
        'description' => 'Inventory',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $generalProductPostingGroupId = (int) DB::table('general_product_posting_groups')->where('code', 'FG')->value('id');
    $inventoryPostingGroupId = (int) DB::table('inventory_posting_groups')->where('code', 'INV')->value('id');

    $item = Item::query()->create([
        'item_code' => 'RM-TEST-001',
        'description' => 'Raw Material Test',
        'item_type' => 'RAW_MATERIAL',
        'inventory_method' => 'FIFO',
        'general_product_posting_group_id' => $generalProductPostingGroupId,
        'inventory_posting_group_id' => $inventoryPostingGroupId,
        'costing_method' => 'AVERAGE',
        'price_calculation_method' => 'STANDARD',
        'unit_cost' => 100,
    ]);

    $order = PurchaseOrder::query()->create([
        'order_number' => 'PO-T-0001',
        'order_type' => 'purchase_order',
        'status' => PurchaseOrderStatus::APPROVED,
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'order_date' => now()->toDateString(),
        'location_id' => $location->id,
        'created_by' => $user->id,
    ]);

    $line = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $order->id,
        'line_number' => 1,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => $quantity,
        'unit_of_measure' => 'PCS',
        'unit_cost' => 100,
        'line_total' => $quantity * 100,
        'total_amount' => $quantity * 100,
    ]);

    return [$order, $line];
}

it('blocks over-receive quantities in service', function () {
    [$order, $line] = makePurchaseOrderWithOneLine(10);

    $service = app(PurchaseOrderService::class);

    expect(fn () => $service->receivePartial($order->id, [[
        'line_id' => $line->id,
        'line_number' => $line->line_number,
        'receive_qty' => 11,
    ]]))->toThrow(Exception::class, 'cannot exceed remaining quantity');
});

it('sets order status to partially received when some quantity is received', function () {
    [$order, $line] = makePurchaseOrderWithOneLine(10);

    $service = app(PurchaseOrderService::class);
    $updated = $service->receivePartial($order->id, [[
        'line_id' => $line->id,
        'line_number' => $line->line_number,
        'receive_qty' => 4,
    ]]);

    expect($updated->status)->toBe(PurchaseOrderStatus::PARTIALLY_RECEIVED)
        ->and((float) $updated->lines->first()->received_quantity)->toBe(4.0);
});

it('sets order status to received when all quantities are fully received', function () {
    [$order, $line] = makePurchaseOrderWithOneLine(10);

    $service = app(PurchaseOrderService::class);
    $updated = $service->receivePartial($order->id, [[
        'line_id' => $line->id,
        'line_number' => $line->line_number,
        'receive_qty' => 10,
    ]]);

    expect($updated->status)->toBe(PurchaseOrderStatus::RECEIVED)
        ->and((float) $updated->lines->first()->received_quantity)->toBe(10.0);
});
