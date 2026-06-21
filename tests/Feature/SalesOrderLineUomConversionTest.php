<?php

use App\Models\ChartOfAccount;
use App\Models\Contact;
use App\Models\Customer;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('calculates quantity_base correctly for CT/PK and preserves manual unit price when UOM changes', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $businessGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'DOM-T',
        'description' => 'Domestic Test',
        'blocked' => false,
    ]);

    $receivablesAccount = ChartOfAccount::factory()->create();
    $customerPostingGroup = CustomerPostingGroup::query()->create([
        'code' => 'CUST-T',
        'description' => 'Customer Test Group',
        'receivables_account_id' => $receivablesAccount->id,
        'blocked' => false,
    ]);
    $contact = Contact::query()->create([
        'name' => 'Test Contact',
        'full_name' => 'Test Contact',
        'type' => 'person',
        'role' => 'customer',
        'email' => 'contact@example.com',
        'phone' => '08000000000',
    ]);

    $customer = Customer::query()->create([
        'customer_number' => 'CUST-T-0001',
        'name' => 'Test Customer',
        'address' => 'Test Address',
        'general_business_posting_group_id' => $businessGroup->id,
        'customer_posting_group_id' => $customerPostingGroup->id,
        'contact_id' => $contact->id,
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

    $uomPcs = UnitOfMeasure::query()->create(['uom_code' => 'PCS', 'description' => 'Pieces']);
    $uomPk = UnitOfMeasure::query()->create(['uom_code' => 'PK', 'description' => 'Pack']);
    $uomCt = UnitOfMeasure::query()->create(['uom_code' => 'CT', 'description' => 'Carton']);

    $item = Item::query()->create([
        'item_code' => 'FG-TEST-001',
        'description' => 'Finished Good Test',
        'item_type' => 'FINISHED_GOOD',
        'inventory_method' => 'FIFO',
        'costing_method' => 'AVERAGE',
        'price_calculation_method' => 'STANDARD',
        'general_product_posting_group_id' => $generalProductPostingGroupId,
        'inventory_posting_group_id' => $inventoryPostingGroupId,
        'base_uom_id' => $uomPcs->id,
        'unit_price' => 10,
        'unit_cost' => 5,
    ]);

    DB::table('item_uom_assignments')->insert([
        ['item_id' => $item->id, 'uom_id' => $uomPcs->id, 'uom_type' => 'BASE', 'conversion_factor' => 1, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
        ['item_id' => $item->id, 'uom_id' => $uomPk->id, 'uom_type' => 'SALES', 'conversion_factor' => 12, 'is_default' => false, 'created_at' => now(), 'updated_at' => now()],
        ['item_id' => $item->id, 'uom_id' => $uomCt->id, 'uom_type' => 'SALES', 'conversion_factor' => 288, 'is_default' => true, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $order = SalesOrder::query()->create([
        'order_number' => 'SO-T-0001',
        'order_type' => 'SALES_ORDER',
        'status' => 'DRAFT',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'customer_address' => $customer->address,
        'order_date' => now()->toDateString(),
        'general_business_posting_group_id' => $businessGroup->id,
        'customer_posting_group_id' => $customerPostingGroup->id,
        'currency_code' => 'NGN',
        'created_by' => $user->id,
    ]);

    $line = SalesOrderLine::query()->create([
        'sales_order_id' => $order->id,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 3,
        'unit_of_measure_code' => 'CT',
        'qty_per_unit_of_measure' => 288,
        'unit_price' => 0,
    ]);

    expect((float) $line->quantity_base)->toBe(864.0)
        ->and((float) $line->unit_price)->toBe(2880.0);

    $line->update(['unit_price' => 2500]);

    $line->update([
        'unit_of_measure_code' => 'PK',
        'qty_per_unit_of_measure' => 12,
    ]);

    $line->refresh();

    expect((float) $line->unit_price)->toBe(2500.0)
        ->and((float) $line->quantity_base)->toBe(36.0);
});
