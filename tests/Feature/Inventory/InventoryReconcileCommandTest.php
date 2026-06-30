<?php

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\GlEntry;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseCreditMemoLine;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostedPurchaseInvoiceLine;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesCreditMemoLine;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseReceipt;
use App\Models\PurchaseReceiptLine;
use App\Models\User;
use App\Models\ValueEntry;
use App\Models\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

it('reports clean inventory ledger state', function (): void {
    $location = Location::factory()->create();
    $item = Item::factory()->create([
        'inventory' => 10,
        'unit_cost' => 12,
    ]);

    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-CLEAN-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => 10,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 120,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
    ]);

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['stock_mismatches'])->toBeEmpty()
        ->and($report['negative_stock_violations'])->toBeEmpty()
        ->and($report['open_item_ledger_entries'])->toBeEmpty()
        ->and($report['missing_value_entries'])->toBeEmpty()
        ->and($report['value_entry_mismatches'])->toBeEmpty();
});

it('reports stock, negative quantity, open entry, missing value, and value mismatch issues', function (): void {
    $location = Location::factory()->create();
    $item = Item::factory()->create([
        'inventory' => 5,
        'unit_cost' => 10,
    ]);

    $entry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::SALE,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'SI-BAD-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => -7,
        'remaining_quantity' => -7,
        'cost_amount_actual' => 70,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => true,
    ]);

    ValueEntry::query()
        ->where('item_ledger_entry_no', $entry->entry_number)
        ->update([
            'quantity' => -6,
            'cost_amount_actual' => 60,
        ]);

    $missingValueEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-MISSING-VALUE-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 10,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
    ]);

    ValueEntry::query()
        ->where('item_ledger_entry_no', $missingValueEntry->entry_number)
        ->delete();

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['stock_mismatches'])->toHaveCount(1)
        ->and($report['stock_mismatches'][0]['classification'])->toBe('stock_cache_mismatch')
        ->and($report['stock_mismatches'][0]['severity'])->toBe('warning')
        ->and($report['negative_stock_violations'])->toHaveCount(1)
        ->and($report['negative_stock_violations'][0]['classification'])->toBe('negative_stock')
        ->and($report['negative_stock_violations'][0]['severity'])->toBe('critical')
        ->and($report['open_item_ledger_entries'])->toHaveCount(1)
        ->and($report['open_item_ledger_entries'][0]['classification'])->toBe('open_item_ledger_entry')
        ->and($report['open_item_ledger_entries'][0]['severity'])->toBe('info')
        ->and($report['missing_value_entries'])->toHaveCount(1)
        ->and($report['missing_value_entries'][0]['classification'])->toBe('value_entry_mismatch')
        ->and($report['missing_value_entries'][0]['severity'])->toBe('critical')
        ->and($report['value_entry_mismatches'])->toHaveCount(1);
});

it('prints detailed diagnostic rows only when requested', function (): void {
    $location = Location::factory()->create();
    $item = Item::factory()->create([
        'item_code' => 'DETAIL-ITEM',
        'description' => 'Detailed Item',
        'inventory' => 5,
        'location_id' => $location->id,
    ]);

    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::SALE,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'SI-DETAIL-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => -2,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 20,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
    ]);

    expect(Artisan::call('biwms:inventory-reconcile'))->toBe(0);
    expect(Artisan::output())
        ->toContain('Item stock field vs item ledger sum mismatches: 1')
        ->toContain('Run with --details to show rows.')
        ->not->toContain('DETAIL-ITEM (');

    expect(Artisan::call('biwms:inventory-reconcile', ['--details' => true]))->toBe(0);
    expect(Artisan::output())
        ->toContain('DETAIL-ITEM (')
        ->toContain('stock=5.0000 ledger=-2.0000 difference=7.0000');
});

it('requires posted sales invoice inventory lines to link to an item ledger entry', function (): void {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();
    $location = Location::factory()->create();
    $item = Item::factory()->create([
        'inventory' => 0,
        'unit_cost' => 12,
        'location_id' => $location->id,
    ]);

    $postedInvoice = PostedSalesInvoice::query()->create([
        'document_number' => 'SI-MISSING-LINK-001',
        'customer_id' => $customer->id,
        'customer_name' => 'Inventory Customer',
        'location_id' => $location->id,
        'posting_date' => now(),
        'document_date' => now(),
        'due_date' => now(),
        'subtotal' => 100,
        'total_amount' => 100,
        'grand_total' => 100,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);

    $postedLine = PostedSalesInvoiceLine::query()->create([
        'posted_sales_invoice_id' => $postedInvoice->id,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'item_description' => $item->description,
        'posting_date' => now(),
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_price' => 100,
        'unit_cost' => 12,
        'unit_cost_lcy' => 12,
        'line_total' => 100,
        'line_amount' => 100,
        'amount_including_vat' => 100,
        'cost_amount' => 12,
        'profit_amount' => 88,
        'line_number' => 10000,
    ]);

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['missing_item_ledger_entries_for_posted_documents'])->toHaveCount(1)
        ->and($report['missing_item_ledger_entries_for_posted_documents'][0]['document_number'])->toBe('SI-MISSING-LINK-001');

    $ledgerEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::SALE,
        'document_type' => 'SALES_ORDER_SHIPMENT',
        'document_number' => 'SS-LINK-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => -1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 12,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
    ]);

    $postedLine->update(['item_ledger_entry_id' => $ledgerEntry->id]);

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['missing_item_ledger_entries_for_posted_documents'])->toBeEmpty();
});

it('exports JSON reconciliation reports with cleanup classifications and remediation guidance', function (): void {
    $exportPath = 'storage/app/testing/inventory-reconcile-export.json';
    File::delete(base_path($exportPath));

    $location = Location::factory()->create();
    $item = Item::factory()->create([
        'item_code' => 'EXPORT-ITEM',
        'description' => 'Export Item',
        'inventory' => 5,
        'location_id' => $location->id,
    ]);

    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::SALE,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'SI-EXPORT-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => -2,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 20,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
    ]);

    expect(Artisan::call('biwms:inventory-reconcile', [
        '--details' => true,
        '--export' => $exportPath,
    ]))->toBe(0);

    expect(File::exists(base_path($exportPath)))->toBeTrue();

    $report = json_decode(File::get(base_path($exportPath)), true);

    expect($report['stock_mismatches'])->toHaveCount(1)
        ->and($report['stock_mismatches'][0]['classification'])->toBe('stock_cache_mismatch')
        ->and($report['stock_mismatches'][0]['severity'])->toBe('warning')
        ->and($report['stock_mismatches'][0]['suggested_remediation'])->toContain('do not edit ledger history directly')
        ->and($report['negative_stock_violations'])->toHaveCount(1)
        ->and($report['negative_stock_violations'][0]['classification'])->toBe('negative_stock')
        ->and($report['negative_stock_violations'][0]['severity'])->toBe('critical')
        ->and($report['negative_stock_violations'][0]['suggested_remediation'])->toContain('approved inventory adjustment');
});

it('reports purchase receipt invoice and vendor ledger reconciliation issues', function (): void {
    $user = User::factory()->create();
    $vendor = Vendor::factory()->create();
    $location = Location::factory()->create();
    $item = Item::factory()->create([
        'item_code' => 'PI-REC-ITEM',
        'inventory' => 0,
        'unit_cost' => 12,
        'location_id' => $location->id,
    ]);

    $postedInvoice = PostedPurchaseInvoice::query()->create([
        'document_number' => 'PPI-MISS-001',
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'location_id' => $location->id,
        'posting_date' => now(),
        'document_date' => now(),
        'due_date' => now(),
        'total_amount' => 100,
        'total_vat' => 0,
        'grand_total' => 100,
        'remaining_amount' => 100,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);

    PostedPurchaseInvoiceLine::query()->create([
        'posted_purchase_invoice_id' => $postedInvoice->id,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'item_description' => $item->description,
        'posting_date' => now(),
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_cost' => 100,
        'unit_cost_lcy' => 100,
        'line_total' => 100,
        'amount_including_vat' => 100,
        'line_number' => 10000,
    ]);

    $receipt = PurchaseReceipt::query()->create([
        'document_number' => 'PR-OVER-001',
        'vendor_id' => $vendor->id,
        'posting_date' => now(),
        'document_date' => now(),
        'receiving_location_id' => $location->id,
        'posted' => true,
        'posted_at' => now(),
        'posted_by' => $user->id,
    ]);

    PurchaseReceiptLine::query()->create([
        'purchase_receipt_id' => $receipt->id,
        'line_number' => 10000,
        'type' => 'ITEM',
        'no' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'quantity_received' => 1,
        'quantity_invoiced' => 2,
        'direct_unit_cost' => 100,
        'line_amount' => 100,
    ]);

    $directInvoice = PurchaseInvoice::query()->create([
        'document_number' => 'PI-DUP-LEDGER-001',
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'location_id' => $location->id,
        'posting_date' => now(),
        'document_date' => now(),
        'due_date' => now(),
        'status' => 'posted',
        'total_amount' => 100,
        'total_vat' => 0,
        'grand_total' => 100,
        'remaining_amount' => 100,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);

    foreach ([1, 2] as $index) {
        ItemLedgerEntry::query()->create([
            'entry_type' => ItemLedgerEntryType::PURCHASE,
            'document_type' => 'PURCHASE_INVOICE',
            'document_number' => $directInvoice->document_number,
            'document_line_number' => 10000,
            'item_id' => $item->id,
            'location_id' => $location->id,
            'quantity' => 1,
            'remaining_quantity' => 1,
            'cost_amount_actual' => 100,
            'cost_amount_expected' => 0,
            'source_id' => $directInvoice->id,
            'source_type' => PurchaseInvoice::class,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
            'posting_date' => now(),
            'entry_date' => now(),
            'open' => $index === 1,
        ]);
    }

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['missing_item_ledger_entries_for_posted_documents'])->toHaveCount(1)
        ->and($report['missing_item_ledger_entries_for_posted_documents'][0]['document_number'])->toBe('PPI-MISS-001')
        ->and($report['missing_item_ledger_entries_for_posted_documents'][0]['classification'])->toBe('missing_item_ledger_link')
        ->and($report['purchase_receipt_lines_over_invoiced'])->toHaveCount(1)
        ->and($report['purchase_receipt_lines_over_invoiced'][0]['classification'])->toBe('purchase_receipt_line_over_invoiced')
        ->and($report['direct_purchase_invoice_duplicate_inventory_entries'])->toHaveCount(1)
        ->and($report['direct_purchase_invoice_duplicate_inventory_entries'][0]['classification'])->toBe('direct_purchase_invoice_duplicate_inventory')
        ->and($report['posted_purchase_invoices_missing_vendor_ledger'])->toHaveCount(1)
        ->and($report['posted_purchase_invoices_missing_vendor_ledger'][0]['classification'])->toBe('posted_purchase_invoice_missing_vendor_ledger');
});

it('reports credit memo missing ledger and over credited quantity issues', function (): void {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();
    $vendor = Vendor::factory()->create();
    $location = Location::factory()->create();
    $item = Item::factory()->create([
        'item_code' => 'RETURN-REC-ITEM',
        'inventory' => 0,
        'unit_cost' => 12,
        'location_id' => $location->id,
    ]);

    $salesInvoiceEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::SALE,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'SI-CM-REC-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => -1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 12,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
    ]);

    $postedSalesInvoice = PostedSalesInvoice::query()->create([
        'document_number' => 'SI-CM-REC-001',
        'customer_id' => $customer->id,
        'customer_name' => 'Return Customer',
        'location_id' => $location->id,
        'posting_date' => now(),
        'document_date' => now(),
        'due_date' => now(),
        'subtotal' => 100,
        'total_amount' => 100,
        'grand_total' => 100,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);

    PostedSalesInvoiceLine::query()->create([
        'posted_sales_invoice_id' => $postedSalesInvoice->id,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'item_description' => $item->description,
        'posting_date' => now(),
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_price' => 100,
        'unit_cost' => 12,
        'unit_cost_lcy' => 12,
        'line_total' => 100,
        'line_amount' => 100,
        'amount_including_vat' => 100,
        'cost_amount' => 12,
        'profit_amount' => 88,
        'line_number' => 10000,
        'item_ledger_entry_id' => $salesInvoiceEntry->id,
    ]);

    $postedSalesCreditMemo = PostedSalesCreditMemo::query()->create([
        'document_number' => 'SCM-REC-001',
        'corrected_invoice_id' => $postedSalesInvoice->id,
        'corrected_invoice_number' => $postedSalesInvoice->document_number,
        'customer_id' => $customer->id,
        'customer_name' => 'Return Customer',
        'general_business_posting_group_id' => $customer->general_business_posting_group_id,
        'customer_posting_group_id' => $customer->customer_posting_group_id,
        'location_id' => $location->id,
        'posting_date' => now(),
        'document_date' => now(),
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'total_amount' => -200,
        'grand_total' => -200,
        'remaining_amount' => 200,
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);

    PostedSalesCreditMemoLine::query()->create([
        'posted_sales_credit_memo_id' => $postedSalesCreditMemo->id,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'item_description' => $item->description,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'quantity' => -2,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => -2,
        'unit_price' => 100,
        'line_total' => -200,
        'line_amount' => -200,
        'amount_including_vat' => -200,
        'posting_date' => now(),
        'line_number' => 10000,
    ]);

    $purchaseInvoiceEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-CM-REC-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 1,
        'cost_amount_actual' => 100,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => true,
    ]);

    $postedPurchaseInvoice = PostedPurchaseInvoice::query()->create([
        'document_number' => 'PI-CM-REC-001',
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'location_id' => $location->id,
        'posting_date' => now(),
        'document_date' => now(),
        'due_date' => now(),
        'total_amount' => 100,
        'total_vat' => 0,
        'grand_total' => 100,
        'remaining_amount' => 100,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);

    PostedPurchaseInvoiceLine::query()->create([
        'posted_purchase_invoice_id' => $postedPurchaseInvoice->id,
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'item_description' => $item->description,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_cost' => 100,
        'unit_cost_lcy' => 100,
        'line_total' => 100,
        'amount_including_vat' => 100,
        'line_number' => 10000,
        'item_ledger_entry_id' => $purchaseInvoiceEntry->id,
    ]);

    $postedPurchaseCreditMemo = PostedPurchaseCreditMemo::query()->create([
        'document_number' => 'PCM-REC-001',
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'posting_date' => now(),
        'document_date' => now(),
        'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
        'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'subtotal' => 200,
        'grand_total' => 200,
        'posted' => true,
        'posted_at' => now(),
        'posted_by' => $user->id,
        'corrects_invoice_number' => $postedPurchaseInvoice->document_number,
    ]);

    PostedPurchaseCreditMemoLine::query()->create([
        'credit_memo_id' => $postedPurchaseCreditMemo->id,
        'line_number' => 10000,
        'type' => 'ITEM',
        'item_id' => $item->id,
        'description' => $item->description,
        'quantity' => 2,
        'unit_of_measure' => 'PCS',
        'unit_price' => 100,
        'amount' => 200,
        'line_total' => 200,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
    ]);

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['missing_item_ledger_entries_for_posted_documents'])->toHaveCount(2)
        ->and(collect($report['missing_item_ledger_entries_for_posted_documents'])->pluck('classification')->all())
        ->toContain('credit_memo_missing_item_ledger_entry', 'return_document_missing_item_ledger_entry')
        ->and($report['sales_credit_memo_lines_over_invoiced'])->toHaveCount(1)
        ->and($report['sales_credit_memo_lines_over_invoiced'][0]['classification'])->toBe('credited_quantity_exceeds_invoiced_quantity')
        ->and($report['purchase_credit_memo_lines_over_invoiced'])->toHaveCount(1)
        ->and($report['purchase_credit_memo_lines_over_invoiced'][0]['classification'])->toBe('returned_quantity_exceeds_received_quantity');
});

it('reports production output consumption value entry and open wip inconsistencies', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $location = Location::factory()->create(['code' => 'MAIN']);
    $item = Item::factory()->create([
        'item_code' => 'FG-WIP-REC',
        'inventory' => 0,
        'location_id' => $location->id,
    ]);

    $wipAccount = ChartOfAccount::query()->create([
        'account_number' => '1210-WIPREC',
        'name' => 'WIP Reconcile',
        'account_category' => 'asset',
        'income_balance' => 0,
    ]);

    InventoryPostingSetup::query()->create([
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'location_id' => $location->id,
        'inventory_account_id' => $wipAccount->id,
        'wip_account_id' => $wipAccount->id,
    ]);

    $releasedOrder = ProductionOrder::query()->create([
        'document_number' => 'PO-WIP-REC-001',
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => $item->id,
        'description' => 'Output without consumption',
        'quantity' => 1,
        'quantity_base' => 1,
        'starting_date_time' => now(),
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'costing_method' => 'FIFO',
        'unit_cost' => 0,
        'cost_rollup' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
    ]);

    $outputEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $releasedOrder->document_number,
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 1,
        'cost_amount_actual' => 25,
        'cost_amount_expected' => 0,
        'source_id' => $releasedOrder->id,
        'source_type' => ProductionOrder::class,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => true,
    ]);

    ValueEntry::query()
        ->where('item_ledger_entry_no', $outputEntry->entry_number)
        ->delete();

    $finishedOrder = ProductionOrder::query()->create([
        'document_number' => 'PO-WIP-REC-002',
        'status' => ProductionOrderStatus::FINISHED,
        'item_id' => $item->id,
        'description' => 'Finished with open WIP',
        'quantity' => 1,
        'quantity_base' => 1,
        'starting_date_time' => now(),
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'costing_method' => 'FIFO',
        'unit_cost' => 0,
        'cost_rollup' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
        'posted' => true,
        'posted_at' => now(),
        'posted_by' => $user->id,
        'finished_at' => now(),
        'finished_by' => $user->id,
    ]);

    GlEntry::query()->create([
        'entry_number' => (GlEntry::query()->max('entry_number') ?? 0) + 1,
        'transaction_number' => (GlEntry::query()->max('transaction_number') ?? 0) + 1,
        'chart_of_account_id' => $wipAccount->id,
        'debit_amount' => 100,
        'credit_amount' => 0,
        'amount' => 100,
        'source_type' => 'ITEM',
        'source_number' => $finishedOrder->document_number,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $finishedOrder->document_number,
        'document_date' => now(),
        'posting_date' => now(),
        'description' => 'Uncleared WIP',
    ]);

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['production_orders_with_output_without_consumption'])->toHaveCount(1)
        ->and($report['production_orders_with_output_without_consumption'][0]['classification'])->toBe('production_output_without_consumption')
        ->and($report['production_orders_with_output_without_consumption'][0]['severity'])->toBe('critical')
        ->and($report['production_output_without_value_entries'])->toHaveCount(1)
        ->and($report['production_output_without_value_entries'][0]['classification'])->toBe('production_output_without_value_entry')
        ->and($report['production_output_without_value_entries'][0]['severity'])->toBe('critical')
        ->and($report['finished_production_orders_with_open_wip'])->toHaveCount(1)
        ->and($report['finished_production_orders_with_open_wip'][0]['classification'])->toBe('finished_production_order_open_wip')
        ->and($report['finished_production_orders_with_open_wip'][0]['severity'])->toBe('critical');
});
